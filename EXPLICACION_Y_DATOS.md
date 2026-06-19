# Guía del Proyecto F.I.G. (Funcionamiento Íntegro Gastronómico)
Este documento explica detalladamente la arquitectura, flujo y base de datos del sistema, y proporciona un **esquema SQL completo junto con datos de prueba funcionales (seeding)** para probar toda la aplicación.

---

## 1. Arquitectura y Estructura del Proyecto

El sistema está desarrollado con una arquitectura desacoplada:
*   **Backend (API):** Laravel 11 + PostgreSQL (Supabase)
    *   Ubicación: `backend-laravel/`
    *   Usa controladores de API que retornan recursos en formato JSON.
    *   Utiliza UUIDs como llaves primarias en todas sus tablas para integrarse de forma flexible.
*   **Frontend (SPA):** JavaScript Vanilla modular (ES6 Modules) + CSS Vanilla.
    *   Ubicación: `frontend/`
    *   Servido en el puerto `5173`.
    *   `App.js` gestiona la inicialización de vistas mediante un `ViewManager`.
    *   `ApiClient.js` centraliza las peticiones a la API del backend (`http://localhost:8000`).

---

## 2. Flujo de Trabajo y Módulos

El sistema opera bajo tres perfiles clave de usuario (`role`):

1.  **Mesero (`waiter`):**
    *   **Salón de Mesas:** Visualiza y administra la disposición física de las mesas (`RestaurantTable`). Puede cambiar estados, iniciar sesiones de consumo (`session_id`) y **fusionar mesas** (`table_groups`).
    *   **Toma de Pedidos:** Crea una comanda (`orders` y `order_items`) asociada a una mesa/sesión. Al crearse, el backend valida el stock en tiempo real y descuenta atómicamente los insumos del inventario (`ingredients` -> `recipe_ingredients`), registrando la auditoría en `inventory_logs`.
    *   **Estados de Comanda:** Manda pedidos a cocina. Cuando cocina los marca listos, los entrega en la mesa (`deliver`) y procesa el cobro (`pay`), liberando las mesas del salón al estado `available`.

2.  **Cocina/KDS (`chef`):**
    *   **Pantalla de Cocina (KDS):** Monitorea las comandas en tiempo real.
    *   Visualiza pedidos con estados `pending` (por preparar) y `preparing` (en cocina).
    *   Muestra el cálculo dinámico de tiempos de preparación (`minutes_pending` y clases visuales de semáforo según retraso) para asegurar un servicio ágil.
    *   Permite marcar platos/pedidos como "Listos" (`ready`) para que el mesero los retire.

3.  **Administración (`admin`):**
    *   **Panel de Gestión:** Creación y edición de usuarios, platos (`products`), categorías y control de insumos de inventario (`ingredients`).
    *   **Alertas de Inventario:** Panel que destaca ingredientes con stock por debajo de su umbral crítico (`low_stock_threshold`).
    *   **Dashboard Estadístico:** Reporte diario de ingresos consolidados (`daily_revenue`), cantidad de pedidos y los platos más vendidos (Top 5).

---

## 3. Esquema de Base de Datos (PostgreSQL DDL)

A continuación se detalla la estructura física de las tablas sincronizada con los modelos de Laravel y el saneamiento estructural (`supabase_sanitization.sql`).

```sql
-- Habilitar extensión para generación de UUIDs si no está habilitada
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Tabla de Usuarios
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'waiter' CHECK (role IN ('admin', 'waiter', 'chef')),
    is_active BOOLEAN DEFAULT true,
    station VARCHAR(100) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabla de Categorías
CREATE TABLE IF NOT EXISTS categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabla de Ingredientes (Inventario/Bodega)
CREATE TABLE IF NOT EXISTS ingredients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL, -- 'gr', 'ml', 'unidades', etc.
    low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tabla de Productos (Platos / Bebidas del Menú)
CREATE TABLE IF NOT EXISTS products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    image_url TEXT NULL,
    is_available BOOLEAN DEFAULT true,
    preparation_time INTEGER NOT NULL DEFAULT 15, -- en minutos
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Relación Recetario (Ingredientes Requeridos por Producto)
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    ingredient_id UUID NOT NULL REFERENCES ingredients(id) ON DELETE RESTRICT,
    quantity DECIMAL(10,3) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_product_ingredient_recipe UNIQUE (product_id, ingredient_id)
);

-- 6. Tabla de Mesas del Restaurant
CREATE TABLE IF NOT EXISTS restaurant_tables (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_number INTEGER UNIQUE NOT NULL,
    capacity INTEGER NOT NULL DEFAULT 2,
    status VARCHAR(20) NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'occupied', 'reserved', 'maintenance')),
    position_x INTEGER NOT NULL DEFAULT 0,
    position_y INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. Tabla de Grupos/Fusión de Mesas
CREATE TABLE IF NOT EXISTS table_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id VARCHAR(100) NOT NULL,
    table_id UUID NOT NULL REFERENCES restaurant_tables(id) ON DELETE CASCADE,
    is_main BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (session_id, table_id)
);
CREATE INDEX IF NOT EXISTS idx_table_groups_session ON table_groups(session_id);

-- 8. Tabla de Órdenes / Comandas
CREATE TABLE IF NOT EXISTS orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    table_id UUID NULL REFERENCES restaurant_tables(id) ON DELETE SET NULL,
    session_id VARCHAR(100) NULL,
    user_id UUID NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'preparing', 'ready', 'delivered', 'paid', 'cancelled')),
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    paid_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 9. Detalle de Ítems de la Orden
CREATE TABLE IF NOT EXISTS order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id UUID NULL REFERENCES products(id) ON DELETE SET NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 10. Bitácora de Auditoría del Inventario
CREATE TABLE IF NOT EXISTS inventory_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ingredient_id UUID NOT NULL REFERENCES ingredients(id) ON DELETE CASCADE,
    order_id UUID NULL REFERENCES orders(id) ON DELETE SET NULL,
    user_id UUID NULL REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(20) NOT NULL CHECK (action IN ('add', 'remove', 'adjust', 'expired')),
    quantity DECIMAL(10,2) NOT NULL,
    stock_before DECIMAL(10,2) NOT NULL,
    stock_after DECIMAL(10,2) NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
```

---

## 4. Script de Carga de Datos Iniciales (PostgreSQL Seed SQL)

Ejecuta estas sentencias en tu base de datos (por ejemplo, en la consola SQL de Supabase) para registrar datos reales de prueba. 

*Nota: La contraseña para todos los usuarios creados (`admin`, `waiter1`, `chef1`) es **`password`** (hasheada en formato bcrypt estándar de Laravel).*

```sql
-- Limpieza de datos previos (opcional/desarrollo)
TRUNCATE inventory_logs, order_items, orders, table_groups, recipe_ingredients, products, restaurant_tables, ingredients, categories, users CASCADE;

-- 1. Insertar Usuarios
INSERT INTO users (id, name, username, password, role, is_active, station) VALUES
('a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'Administrador Principal', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', true, 'Oficina'),
('b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'Roberto Mesero', 'waiter1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'waiter', true, 'Salón Principal'),
('c3d4e5f6-a7b8-9c0d-1e2f-3a4b5c6d7e8f', 'Chef Gustavo', 'chef1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chef', true, 'Plancha Caliente');

-- 2. Insertar Categorías
INSERT INTO categories (id, name) VALUES
('d4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', 'Platos de Fondo'),
('e5f6a7b8-c9d0-1e2f-3a4b-5c6d7e8f9a0b', 'Acompañamientos'),
('f6a7b8c9-d0e1-2f3a-4b5c-6d7e8f9a0b1c', 'Bebestibles'),
('a7b8c9d0-e1f2-3a4b-5c6d-7e8f9a0b1c2d', 'Ensaladas');

-- 3. Insertar Ingredientes (Inventario base con niveles de stock óptimos y críticos)
INSERT INTO ingredients (id, name, stock, unit, low_stock_threshold) VALUES
('11111111-1111-1111-1111-111111111111', 'Pan de Hamburguesa Brioche', 100.00, 'unidades', 15.00),
('22222222-2222-2222-2222-222222222222', 'Carne de Vacuno Molida (Premium)', 15.50, 'kg', 5.00),
('33333333-3333-3333-3333-333333333333', 'Laminas Queso Cheddar', 150.00, 'unidades', 30.00),
('44444444-4444-4444-4444-444444444444', 'Papas Naturales Picadas', 40.00, 'kg', 10.00),
('55555555-5555-5555-5555-555555555555', 'Lechuga Costina', 3.00, 'kg', 5.00), -- Alerta Stock Bajo
('66666666-6666-6666-6666-666666666666', 'Tomates de Huerto', 1.20, 'kg', 4.00),   -- Alerta Stock Crítico
('77777777-7777-7777-7777-777777777777', 'Lata Coca-Cola 350ml', 80.00, 'unidades', 12.00);

-- 4. Insertar Productos (Platos del Menú)
INSERT INTO products (id, name, description, price, category_id, is_available, preparation_time) VALUES
('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'Hamburguesa Clásica Cheddar', 'Jugosa hamburguesa de 150g, queso cheddar fundido, lechuga y tomate en pan brioche.', 9500.00, 'd4e5f6a7-b8c9-0d1e-2f3a-4b5c6d7e8f9a', true, 12),
('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Papas Fritas Rústicas Grandes', 'Papas fritas caseras sazonadas con sal de mar y romero.', 3500.00, 'e5f6a7b8-c9d0-1e2f-3a4b-5c6d7e8f9a0b', true, 8),
('cccccccc-cccc-cccc-cccc-cccccccccccc', 'Ensalada César con Aderezo', 'Fresca lechuga, crutones, queso parmesano y delicioso aderezo césar.', 6000.00, 'a7b8c9d0-e1f2-3a4b-5c6d-7e8f9a0b1c2d', true, 6),
('dddddddd-dddd-dddd-dddd-dddddddddddd', 'Coca-Cola Helada', 'Lata refrescante de Coca-Cola original acompañada de vaso con hielo.', 2000.00, 'f6a7b8c9-d0e1-2f3a-4b5c-6d7e8f9a0b1c', true, 2);

-- 5. Insertar Recetario (Relaciona platos con sus ingredientes exactos)
INSERT INTO recipe_ingredients (id, product_id, ingredient_id, quantity, unit) VALUES
(gen_random_uuid(), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '11111111-1111-1111-1111-111111111111', 1.000, 'unidades'), -- 1 Pan
(gen_random_uuid(), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '22222222-2222-2222-2222-222222222222', 0.150, 'kg'),       -- 150gr Carne
(gen_random_uuid(), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '33333333-3333-3333-3333-333333333333', 2.000, 'unidades'), -- 2 Quesos
(gen_random_uuid(), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '55555555-5555-5555-5555-555555555555', 0.020, 'kg'),       -- 20gr Lechuga
(gen_random_uuid(), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', '66666666-6666-6666-6666-666666666666', 0.040, 'kg'),       -- 40gr Tomate

(gen_random_uuid(), 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', '44444444-4444-4444-4444-444444444444', 0.300, 'kg'),       -- 300gr Papas

(gen_random_uuid(), 'cccccccc-cccc-cccc-cccc-cccccccccccc', '55555555-5555-5555-5555-555555555555', 0.150, 'kg'),       -- 150gr Lechuga

(gen_random_uuid(), 'dddddddd-dddd-dddd-dddd-dddddddddddd', '77777777-7777-7777-7777-777777777777', 1.000, 'unidades'); -- 1 Lata

-- 6. Insertar Mesas del Salón (Ubicaciones en plano y capacidades físicas)
INSERT INTO restaurant_tables (id, table_number, capacity, status, position_x, position_y) VALUES
('99999999-9999-9999-9999-999999999901', 1, 4, 'occupied', 100, 120),  -- Ocupada
('99999999-9999-9999-9999-999999999902', 2, 4, 'occupied', 100, 240),  -- Ocupada (Fusionada con Mesa 1)
('99999999-9999-9999-9999-999999999903', 3, 2, 'available', 280, 120), -- Disponible
('99999999-9999-9999-9999-999999999904', 4, 6, 'maintenance', 280, 240),-- Mantenimiento
('99999999-9999-9999-9999-999999999905', 5, 4, 'reserved', 460, 120),   -- Reservada
('99999999-9999-9999-9999-999999999906', 6, 2, 'available', 460, 240);  -- Disponible

-- 7. Configurar Fusión de Mesas Activa (Mesa 1 y Mesa 2 están en un grupo)
INSERT INTO table_groups (id, session_id, table_id, is_main) VALUES
(gen_random_uuid(), 'session-fusion-12', '99999999-9999-9999-9999-999999999901', true),  -- Mesa 1 (Principal)
(gen_random_uuid(), 'session-fusion-12', '99999999-9999-9999-9999-999999999902', false); -- Mesa 2

-- 8. Insertar Comandas Históricas y Activas (Muestra de todos los estados del ciclo de cocina/KDS)
-- Orden A: PENDIENTE (Mesa 1 fusionada - Creada hace 5 minutos)
INSERT INTO orders (id, table_id, session_id, user_id, status, subtotal, total, notes, created_at, updated_at) VALUES
('aaaa1111-bbbb-cccc-dddd-eeeeffff0001', '99999999-9999-9999-9999-999999999901', 'session-fusion-12', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'pending', 15000.00, 15000.00, 'Hamburguesas bien cocidas por favor.', CURRENT_TIMESTAMP - INTERVAL '5 minutes', CURRENT_TIMESTAMP - INTERVAL '5 minutes');

-- Orden B: EN PREPARACIÓN (Cocina/KDS - Creada hace 18 minutos)
INSERT INTO orders (id, table_id, session_id, user_id, status, subtotal, total, notes, created_at, updated_at) VALUES
('aaaa1111-bbbb-cccc-dddd-eeeeffff0002', '99999999-9999-9999-9999-999999999901', 'session-fusion-12', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'preparing', 25000.00, 25000.00, 'Sin hielo las bebidas.', CURRENT_TIMESTAMP - INTERVAL '18 minutes', CURRENT_TIMESTAMP - INTERVAL '15 minutes');

-- Orden C: LISTO PARA ENTREGA (Salón - Creada hace 25 minutos)
INSERT INTO orders (id, table_id, session_id, user_id, status, subtotal, total, notes, created_at, updated_at) VALUES
('aaaa1111-bbbb-cccc-dddd-eeeeffff0003', '99999999-9999-9999-9999-999999999905', NULL, 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'ready', 8000.00, 8000.00, 'Aderezo césar extra al lado.', CURRENT_TIMESTAMP - INTERVAL '25 minutes', CURRENT_TIMESTAMP - INTERVAL '10 minutes');

-- Orden D: ENTREGADO (Espera cobro - Creada hace 45 minutos)
INSERT INTO orders (id, table_id, session_id, user_id, status, subtotal, total, notes, created_at, updated_at) VALUES
('aaaa1111-bbbb-cccc-dddd-eeeeffff0004', '99999999-9999-9999-9999-999999999901', 'session-fusion-12', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'delivered', 11500.00, 11500.00, NULL, CURRENT_TIMESTAMP - INTERVAL '45 minutes', CURRENT_TIMESTAMP - INTERVAL '30 minutes');

-- Orden E: PAGADA (Historial e ingresos del día - Pagada Hoy hace 1 hora)
INSERT INTO orders (id, table_id, session_id, user_id, status, subtotal, total, notes, paid_at, created_at, updated_at) VALUES
('aaaa1111-bbbb-cccc-dddd-eeeeffff0005', NULL, NULL, 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'paid', 21500.00, 21500.00, 'Excelente servicio.', CURRENT_TIMESTAMP - INTERVAL '1 hour', CURRENT_TIMESTAMP - INTERVAL '1 hour', CURRENT_TIMESTAMP - INTERVAL '1 hour');

-- 9. Insertar Detalle de Items de Órdenes
INSERT INTO order_items (id, order_id, product_id, quantity, unit_price, subtotal, notes) VALUES
-- Items de Orden A (Hamburguesa Clásica + Papas rusticas = 13000, modificado para calzar subtotal de 15000 con Coca-Cola)
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0001', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 1, 9500.00, 9500.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0001', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 1, 3500.00, 3500.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0001', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 2000.00, 2000.00, NULL),

-- Items de Orden B (2 Hamburguesas + 1 Papas + 1 Coca-Cola = 24500, modificado para calzar 25000 con adiciones ficticias o ajuste simple)
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0002', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 2, 9500.00, 19000.00, 'Término medio.'),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0002', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 1, 3500.00, 3500.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0002', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 2000.00, 2000.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0002', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 500.00, 500.00, 'Hielo extra'), -- Ajuste a 25000

-- Items de Orden C (1 Ensalada César + 1 Coca-Cola = 8000)
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0003', 'cccccccc-cccc-cccc-cccc-cccccccccccc', 1, 6000.00, 6000.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0003', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 2000.00, 2000.00, NULL),

-- Items de Orden D (1 Hamburguesa + 1 Coca-Cola = 11500)
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0004', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 1, 9500.00, 9500.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0004', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 2000.00, 2000.00, NULL),

-- Items de Orden E (2 Hamburguesas + 1 Papas = 22500, modificado para calzar 21500)
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0005', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 2, 9500.00, 19000.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0005', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 2000.00, 2000.00, NULL),
(gen_random_uuid(), 'aaaa1111-bbbb-cccc-dddd-eeeeffff0005', 'dddddddd-dddd-dddd-dddd-dddddddddddd', 1, 500.00, 500.00, 'Descuento'); -- Ajuste a 21500

-- 10. Insertar Historial de Movimientos de Inventario (Bitácora)
INSERT INTO inventory_logs (id, ingredient_id, order_id, user_id, action, quantity, stock_before, stock_after, reason) VALUES
(gen_random_uuid(), '11111111-1111-1111-1111-111111111111', NULL, 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'add', 100.00, 0.00, 100.00, 'Inventario inicial del sistema cargado por el Administrador.'),
(gen_random_uuid(), '22222222-2222-2222-2222-222222222222', NULL, 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d', 'add', 15.50, 0.00, 15.50, 'Inventario inicial de carnes.'),
(gen_random_uuid(), '11111111-1111-1111-1111-111111111111', 'aaaa1111-bbbb-cccc-dddd-eeeeffff0001', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'remove', 1.00, 100.00, 99.00, 'Comanda #aaaa1111... - Descuento por Hamburguesa Clásica Cheddar x1'),
(gen_random_uuid(), '22222222-2222-2222-2222-222222222222', 'aaaa1111-bbbb-cccc-dddd-eeeeffff0001', 'b2c3d4e5-f6a7-8b9c-0d1e-2f3a4b5c6d7e', 'remove', 0.15, 15.50, 15.35, 'Comanda #aaaa1111... - Descuento por Hamburguesa Clásica Cheddar x1');
```

---

## 5. Instrucciones para la Prueba en Plenitud del Sistema

Una vez ejecutado el esquema y los datos iniciales, puedes probar el sistema completo utilizando la siguiente guía:

### 1. Inicio de Sesión
*   **Para Administrar:**
    *   **Usuario:** `admin` | **Contraseña:** `password`
    *   **Prueba:** Ve al panel de control para crear nuevos productos, ver alertas críticas de stock (`Tomates` y `Lechuga`) y revisar estadísticas.
*   **Para Atender Mesas (Comandas):**
    *   **Usuario:** `waiter1` | **Contraseña:** `password`
    *   **Prueba:** Abre el plano del salón. Verás mesas ocupadas, reservadas y disponibles.
*   **Para Cocinar:**
    *   **Usuario:** `chef1` | **Contraseña:** `password`
    *   **Prueba:** Observa la pantalla KDS de preparación.

### 2. Toma de Pedidos e Inventario
1.  Inicia sesión como `waiter1`.
2.  Haz clic en la **Mesa 3** (disponible) para abrir su flujo de comanda.
3.  Agrega **1 Hamburguesa Clásica** y **1 Coca-Cola**.
4.  Presiona "Enviar Pedido". 
    *   *Detrás de escenas:* El backend descuenta del inventario `1 Pan brioche`, `150gr de Carne`, `2 quesos Cheddar` y `1 Coca-Cola`. Registra la auditoría en `inventory_logs`.
    *   *Si intentas pedir 40 Hamburguesas:* El sistema rebotará la comanda indicando que no hay suficiente stock.

### 3. Fusión de Mesas
1.  En la pantalla del mesero, selecciona una mesa libre (ej. **Mesa 6**).
2.  Usa la opción **Juntar Mesas** (Merge) y selecciona la **Mesa 3** y la **Mesa 6**.
3.  Elige cuál será la mesa principal (ej. Mesa 3) y confirma. 
    *   *Detrás de escenas:* Se asocian bajo un mismo `session_id` en `table_groups`. La comanda cargará los consumos de ambas mesas agrupadas en una única cuenta final.

### 4. Tiempos de Cocina (KDS)
1.  Inicia sesión como `chef1`.
2.  Verás el pedido de la **Mesa 1** en estado `Pending` (color verde, recién ingresado) y otro pedido en estado `Preparing` (color amarillo/rojo indicando más de 10-20 minutos de espera).
3.  Haz clic en "Comenzar Preparación" para pasar el pedido a `preparing`.
4.  Haz clic en "Listo" (`mark-ready`). El pedido desaparecerá de tu pantalla KDS y el temporizador se detendrá.

### 5. Entrega y Pago
1.  Como `waiter1`, verás una notificación de que el pedido está listo para ser servido.
2.  Entrega el pedido a los comensales (`deliver`).
3.  Cuando pidan la cuenta, presiona **Cobrar** (`processPayment`). El backend procesará el pago, registrará el ingreso diario y liberará la mesa (o el grupo de mesas fusionadas completo), dejándolas en estado `available` listas para la siguiente sesión.
