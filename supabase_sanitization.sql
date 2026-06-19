-- ============================================================
-- F.I.G - Funcionamiento Íntegro Gastronómico
-- SCRIPT DE SANEAMIENTO ESTRUCTURAL DE BASE DE DATOS (SUPABASE)
-- ============================================================

BEGIN;

-- ============================================================
-- 1. TRIGGERS NATIVOS PARA AUTOMATIZAR updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Aplicar disparador a la tabla: users
DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Aplicar disparador a la tabla: ingredients
DROP TRIGGER IF EXISTS trg_ingredients_updated_at ON ingredients;
CREATE TRIGGER trg_ingredients_updated_at
BEFORE UPDATE ON ingredients
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Aplicar disparador a la tabla: products
DROP TRIGGER IF EXISTS trg_products_updated_at ON products;
CREATE TRIGGER trg_products_updated_at
BEFORE UPDATE ON products
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Aplicar disparador a la tabla: restaurant_tables
DROP TRIGGER IF EXISTS trg_restaurant_tables_updated_at ON restaurant_tables;
CREATE TRIGGER trg_restaurant_tables_updated_at
BEFORE UPDATE ON restaurant_tables
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Aplicar disparador a la tabla: orders
DROP TRIGGER IF EXISTS trg_orders_updated_at ON orders;
CREATE TRIGGER trg_orders_updated_at
BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();


-- ============================================================
-- 2. NORMALIZACIÓN DE CATEGORÍAS (products.category)
-- ============================================================

-- Crear tabla maestra de categorías
CREATE TABLE IF NOT EXISTS categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Trigger updated_at para categories
DROP TRIGGER IF EXISTS trg_categories_updated_at ON categories;
CREATE TRIGGER trg_categories_updated_at
BEFORE UPDATE ON categories
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Migrar categorías existentes desde productos
INSERT INTO categories (name)
SELECT DISTINCT category FROM products
WHERE category IS NOT NULL AND category <> ''
ON CONFLICT (name) DO NOTHING;

-- Crear columna temporal de relación
ALTER TABLE products ADD COLUMN IF NOT EXISTS category_id UUID;

-- Relacionar productos con su nueva categoría ID
UPDATE products p
SET category_id = c.id
FROM categories c
WHERE p.category = c.name;

-- Hacer obligatoria la relación si hay datos (o mantener nullable por seguridad inicial)
-- En este caso la hacemos FOREIGN KEY con ON DELETE SET NULL para evitar bloqueos catastróficos
ALTER TABLE products DROP CONSTRAINT IF EXISTS fk_products_category;
ALTER TABLE products 
ADD CONSTRAINT fk_products_category 
FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Eliminar columna desnormalizada original
ALTER TABLE products DROP COLUMN IF EXISTS category;


-- ============================================================
-- 3. ELIMINACIÓN DE LA TABLA REDUNDANTE recipes
-- ============================================================

-- Agregar relación directa en recipe_ingredients hacia products
ALTER TABLE recipe_ingredients ADD COLUMN IF NOT EXISTS product_id UUID;

-- Traspasar IDs de productos desde la tabla intermedia recipes
UPDATE recipe_ingredients ri
SET product_id = r.product_id
FROM recipes r
WHERE ri.recipe_id = r.id;

-- Asegurar que la nueva columna sea NOT NULL y borrar registros inconsistentes si los hubiera
DELETE FROM recipe_ingredients WHERE product_id IS NULL;
ALTER TABLE recipe_ingredients ALTER COLUMN product_id SET NOT NULL;

-- Crear la relación de clave foránea directa con products
ALTER TABLE recipe_ingredients DROP CONSTRAINT IF EXISTS fk_recipe_ingredients_product;
ALTER TABLE recipe_ingredients 
ADD CONSTRAINT fk_recipe_ingredients_product 
FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

-- Crear restricción de unicidad para evitar duplicar el mismo ingrediente en el mismo producto
ALTER TABLE recipe_ingredients DROP CONSTRAINT IF EXISTS recipe_ingredients_recipe_id_ingredient_id_key;
ALTER TABLE recipe_ingredients DROP CONSTRAINT IF EXISTS uq_product_ingredient_recipe;
ALTER TABLE recipe_ingredients 
ADD CONSTRAINT uq_product_ingredient_recipe 
UNIQUE (product_id, ingredient_id);

-- Eliminar columnas e índices obsoletos
ALTER TABLE recipe_ingredients DROP COLUMN IF EXISTS recipe_id;

-- Eliminar de forma definitiva la tabla redundante recipes
DROP TABLE IF EXISTS recipes CASCADE;


-- ============================================================
-- 4. RESTRUCTURACIÓN DE table_groups (FUSIÓN DE MESAS)
-- ============================================================

-- Eliminamos la estructura errónea previa
DROP TABLE IF EXISTS table_groups CASCADE;

-- Creamos la estructura correcta donde se registran todas las mesas involucradas
CREATE TABLE table_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id VARCHAR(100) NOT NULL,
    table_id UUID NOT NULL REFERENCES restaurant_tables(id) ON DELETE CASCADE,
    is_main BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(session_id, table_id)
);

-- Índice para acelerar búsquedas de mesas agrupadas
CREATE INDEX IF NOT EXISTS idx_table_groups_session ON table_groups(session_id);


-- ============================================================
-- 5. POLÍTICAS DE INTEGRIDAD REFERENCIAL SEGURA (ON DELETE)
-- ============================================================

-- Modificar order_items para permitir borrado suave o nulo si el producto es retirado del menú
-- Esto preserva el historial contable y de ventas en el restaurante
ALTER TABLE order_items ALTER COLUMN product_id DROP NOT NULL;
ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_product_id_fkey;
ALTER TABLE order_items 
ADD CONSTRAINT order_items_product_id_fkey 
FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;

-- Modificar logs de inventario para que persistan si se elimina una orden o un usuario
ALTER TABLE inventory_logs DROP CONSTRAINT IF EXISTS inventory_logs_order_id_fkey;
ALTER TABLE inventory_logs 
ADD CONSTRAINT fk_inventory_logs_order 
FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;

ALTER TABLE inventory_logs DROP CONSTRAINT IF EXISTS inventory_logs_user_id_fkey;
ALTER TABLE inventory_logs 
ADD CONSTRAINT fk_inventory_logs_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Agregar restricciones de integridad en ingredientes (evitar borrar insumos activos)
ALTER TABLE recipe_ingredients DROP CONSTRAINT IF EXISTS recipe_ingredients_ingredient_id_fkey;
ALTER TABLE recipe_ingredients 
ADD CONSTRAINT fk_recipe_ingredients_ingredient 
FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE RESTRICT;


-- ============================================================
-- 6. REDEFINICIÓN DE FUNCIONES Y VISTAS AFECTADAS
-- ============================================================

-- Función: check_product_availability
CREATE OR REPLACE FUNCTION check_product_availability(p_product_id UUID)
RETURNS TABLE (
    available BOOLEAN,
    missing_ingredient VARCHAR,
    required_qty DECIMAL,
    available_qty DECIMAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        CASE 
            WHEN COALESCE(ing.stock, 0) >= ri.quantity_required THEN true
            ELSE false
        END as available,
        COALESCE(ing.name, 'Unknown') as missing_ingredient,
        ri.quantity_required as required_qty,
        COALESCE(ing.stock, 0) as available_qty
    FROM recipe_ingredients ri
    LEFT JOIN ingredients ing ON ri.ingredient_id = ing.id
    WHERE ri.product_id = p_product_id;
END;
$$ LANGUAGE plpgsql;

-- Vista KDS Orders (redefinida por la remoción de recipes)
CREATE OR REPLACE VIEW kds_orders AS
SELECT 
    o.id,
    o.table_id,
    rt.table_number,
    o.status,
    o.created_at,
    EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - o.created_at))/60 as minutes_pending,
    json_agg(json_build_object(
        'product_id', oi.product_id,
        'product_name', p.name,
        'quantity', oi.quantity,
        'notes', oi.notes
    )) as items
FROM orders o
LEFT JOIN restaurant_tables rt ON o.table_id = rt.id
JOIN order_items oi ON o.id = oi.order_id
JOIN products p ON oi.product_id = p.id
WHERE o.status IN ('pending', 'preparing')
GROUP BY o.id, rt.table_number;

COMMIT;
