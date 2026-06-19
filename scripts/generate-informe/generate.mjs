import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  Table,
  TableRow,
  TableCell,
  WidthType,
  AlignmentType,
  ShadingType,
} from 'docx';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputPath = path.resolve(__dirname, '../../Informe_Estado_Avance_3_FIG.docx');

const PLACEHOLDER = (text) => `[COMPLETAR: ${text}]`;

function heading(text, level = HeadingLevel.HEADING_1) {
  return new Paragraph({ text, heading: level, spacing: { before: 240, after: 120 } });
}

function body(text, opts = {}) {
  return new Paragraph({
    spacing: { after: 120 },
    alignment: opts.align,
    children: [new TextRun({ text, bold: opts.bold, italics: opts.italics })],
  });
}

function bullet(text) {
  return new Paragraph({ text, bullet: { level: 0 }, spacing: { after: 80 } });
}

function placeholder(text) {
  return body(text, { italics: true });
}

function headerCell(text) {
  return new TableCell({
    shading: { type: ShadingType.CLEAR, fill: 'D9E2F3' },
    children: [new Paragraph({ children: [new TextRun({ text, bold: true })] })],
  });
}

function cell(text) {
  return new TableCell({
    children: [new Paragraph({ children: [new TextRun({ text: String(text) })] })],
  });
}

function table(headers, rows) {
  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    rows: [
      new TableRow({ children: headers.map((h) => headerCell(h)) }),
      ...rows.map((row) => new TableRow({ children: row.map((c) => cell(c)) })),
    ],
  });
}

const planPruebas = [
  ['PT-01', 'Autenticación', 'Iniciar sesión con usuario válido (admin, waiter, chef)', 'HTTP 200, redirección a vista según rol', 'Manual / Pendiente evidencia'],
  ['PT-02', 'Autenticación', 'Intentar login con credenciales inválidas', 'Mensaje de error, sin acceso al sistema', 'Manual / Pendiente evidencia'],
  ['PT-03', 'Mesero', 'Visualizar mapa de mesas y cambiar estado', 'Mesas reflejan estado available/occupied', 'Manual / Pendiente evidencia'],
  ['PT-04', 'Mesero', 'Crear pedido con productos disponibles', 'Orden creada, stock descontado, ítems registrados', 'Manual / Pendiente evidencia'],
  ['PT-05', 'Mesero', 'Enviar pedido a cocina y entregar', 'Estados pending → preparing → ready → delivered', 'Manual / Pendiente evidencia'],
  ['PT-06', 'Mesero', 'Procesar pago de orden', 'status=paid, paid_at registrado, mesa liberada', 'Manual / Pendiente evidencia'],
  ['PT-07', 'Cocina (KDS)', 'Visualizar cola de pedidos pending/preparing', 'Pedidos visibles con tiempo transcurrido', 'Manual / Pendiente evidencia'],
  ['PT-08', 'Cocina (KDS)', 'Marcar pedido como listo', 'Estado cambia a ready', 'Manual / Pendiente evidencia'],
  ['PT-09', 'Admin', 'Gestionar usuarios (CRUD)', 'Operaciones reflejadas en listado de usuarios', 'Manual / Pendiente evidencia'],
  ['PT-10', 'Admin', 'Control de bodega y restock', 'Stock actualizado y alertas coherentes', 'Manual / Pendiente evidencia'],
  ['PT-11', 'Admin', 'Dashboard ingresos del día', 'Muestra revenue y pedidos completados del día', 'Manual / Pendiente evidencia'],
  ['PT-12', 'Admin', 'Reporte ventas semanales (UI)', 'Panel muestra total, rango lun-dom y desglose diario', 'Manual / Pendiente evidencia'],
  ['PT-13', 'API Reportes', 'GET /api/reports/weekly estructura JSON', 'HTTP 200, 7 días, campos requeridos', 'Automatizada / Aprobada'],
  ['PT-14', 'API Reportes', 'Solo órdenes paid en rango semanal', 'Excluye pending, delivered y fuera de semana', 'Automatizada / Aprobada'],
  ['PT-15', 'API Reportes', 'Días sin ventas en cero', 'revenue=0, order_count=0, formatted_revenue=$0', 'Automatizada / Aprobada'],
  ['PT-16', 'API Reportes', 'Totales coherentes con suma diaria', 'total_revenue y order_count = suma de days', 'Automatizada / Aprobada'],
];

const pruebasAutomatizadas = [
  ['test_weekly_report_returns_success_structure', 'Estructura JSON del endpoint semanal', 'PASS (8/8)'],
  ['test_weekly_report_week_bounds_are_monday_to_sunday', 'Semana calendario lun-dom', 'PASS'],
  ['test_weekly_report_only_includes_paid_orders_in_range', 'Suma solo paid en rango', 'PASS'],
  ['test_weekly_report_excludes_unpaid_orders', 'Excluye pending y delivered', 'PASS'],
  ['test_weekly_report_excludes_paid_orders_outside_week', 'Excluye paid fuera de semana', 'PASS'],
  ['test_weekly_report_fills_missing_days_with_zero', 'Días vacíos en $0', 'PASS'],
  ['test_weekly_report_day_labels_are_lun_to_dom', 'Etiquetas Lun a Dom', 'PASS'],
  ['test_weekly_report_totals_match_sum_of_days', 'Totales = suma de días', 'PASS'],
];

const mejoras = [
  ['Corrección', 'Reporte semanal', 'Query con DATE() PostgreSQL no portable a SQLite en tests', 'Agrupación con Eloquent en PHP (OrderController::weeklyReport)', 'Completitud / Corrección'],
  ['Calidad', 'Pruebas automatizadas', 'Sin suite para reporte semanal', 'WeeklyReportTest.php con 8 casos Feature', 'Corrección / Pertinencia'],
  ['Calidad', 'Factories y migraciones', 'Sin tabla orders en entorno local de test', 'Migración mínima orders + OrderFactory para PHPUnit', 'Completitud'],
  ['Seguridad', 'Validación de entrada', 'Revisión de Form Requests en módulos Auth e Inventory', 'LoginRequest, RegisterRequest, StoreIngredientRequest, etc.', 'Seguridad'],
  ['Usabilidad', 'Panel admin', 'Falta visibilidad de ventas semanales', 'Panel Ventas Semanales con barras y totales en AdminView', 'Usabilidad / Pertinencia'],
  [PLACEHOLDER('Tipo'), PLACEHOLDER('Agregar mejoras post-pruebas manuales'), PLACEHOLDER('Hallazgo'), PLACEHOLDER('Acción'), PLACEHOLDER('Estándar')],
];

const doc = new Document({
  sections: [
    {
      properties: {},
      children: [
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 200 },
          children: [new TextRun({ text: 'INFORME ESTADO DE AVANCE 3', bold: true, size: 32 })],
        }),
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 400 },
          children: [new TextRun({ text: 'Sistema F.I.G. — Funcionamiento Íntegro Gastronómico', bold: true, size: 28 })],
        }),
        body('Institución: ' + PLACEHOLDER('nombre institución')),
        body('Asignatura: ' + PLACEHOLDER('nombre asignatura')),
        body('Equipo: ' + PLACEHOLDER('nombres integrantes')),
        body('Docente guía: ' + PLACEHOLDER('nombre docente')),
        body('Fecha de entrega: Junio 2026'),
        body('Aprobación plan de pruebas por docente: ' + PLACEHOLDER('fecha y firma / evidencia')),

        heading('Índice'),
        bullet('1. Contenido de evaluaciones anteriores (Evaluación 1 y 2)'),
        bullet('2. Descripción del proyecto y arquitectura técnica'),
        bullet('3. Plan de pruebas de software'),
        bullet('4. Aplicación de pruebas de validación'),
        bullet('5. Mejoras al producto'),
        bullet('6. Documentación y configuración'),
        bullet('7. Aceptación y tablero Kanban'),
        bullet('8. Conclusión'),
        bullet('9. Lecciones aprendidas'),
        bullet('10. Anexos y evidencias'),
        bullet('11. Guía de estudio para la presentación oral'),

        heading('1. Contenido de evaluaciones anteriores'),
        placeholder('Insertar aquí el contenido consolidado de la Evaluación 1 y Evaluación 2 según lo exige la rúbrica.'),

        heading('2. Descripción del proyecto y arquitectura técnica'),
        heading('2.1 Problemática', HeadingLevel.HEADING_2),
        body('El sistema F.I.G. (Funcionamiento Íntegro Gastronómico) responde a la necesidad de digitalizar la operación de un restaurante: gestión de mesas, toma de pedidos, cocina (KDS), inventario, administración de usuarios y reportes de ingresos. La problemática central es coordinar mesero, cocina y administración en un flujo único con trazabilidad de stock y ventas, evitando errores manuales (pedidos perdidos, stock desactualizado, ingresos mal calculados).'),

        heading('2.2 Arquitectura general (cliente-servidor desacoplada)', HeadingLevel.HEADING_2),
        body('El proyecto adopta una arquitectura de tres capas desacopladas, lo que permite que el frontend y el backend evolucionen de forma independiente:'),
        bullet('Capa de presentación (frontend): SPA en JavaScript vanilla, carpeta frontend/. Se ejecuta en el navegador del usuario (puerto 5173 en desarrollo). No accede directamente a la base de datos.'),
        bullet('Capa de lógica de negocio (backend): API REST en Laravel 13, carpeta backend-laravel/. Expone endpoints JSON bajo el prefijo /api. Contiene controladores, validaciones, modelos Eloquent y reglas de negocio (descuento de stock, ciclo de órdenes, reportes).'),
        bullet('Capa de datos (persistencia): PostgreSQL alojado en Supabase. Almacena usuarios, mesas, productos, ingredientes, órdenes, ítems, recetas y bitácora de inventario. Usa UUID como clave primaria en tablas principales.'),
        body('Este desacoplamiento implica que cualquier cambio en la interfaz (colores, disposición de paneles) no obliga a modificar la base de datos, y que la misma API podría servir en el futuro a una app móvil u otro cliente sin reescribir la lógica de negocio.'),

        heading('2.3 Comunicación frontend ↔ backend (ApiClient)', HeadingLevel.HEADING_2),
        body('Toda comunicación entre el SPA y el servidor pasa por una única clase: frontend/js/api/ApiClient.js. Esta clase encapsula el protocolo HTTP y evita que cada vista repita código de fetch. El flujo de una petición es el siguiente:'),
        bullet('1. Una vista (WaiterView, KitchenView o AdminView) invoca un método del ApiClient, por ejemplo api.getWeeklyRevenue() o api.login(username, password).'),
        bullet('2. ApiClient construye la URL completa: baseUrl + endpoint (ej. http://localhost:8000 + /api/reports/weekly).'),
        bullet('3. El método interno _request() ejecuta fetch() con método HTTP (GET, POST, PATCH, PUT, DELETE), cabecera Content-Type: application/json y, si existe sesión, Authorization: Bearer {token}.'),
        bullet('4. Laravel recibe la petición en routes/api.php, la enruta al controlador correspondiente y devuelve JSON con estructura { success: true/false, data: ... } o mensaje de error.'),
        bullet('5. ApiClient parsea la respuesta; si response.ok es falso, lanza error; si no, retorna el objeto JSON a la vista, que actualiza el DOM (render*).'),
        body('La clase App.js instancia un único ApiClient apuntando a http://localhost:8000 y lo inyecta en las tres vistas al iniciar sesión. Tras el login exitoso, el token se guarda en localStorage (auth_token) y se reutiliza en peticiones posteriores.'),

        heading('2.4 Organización del frontend', HeadingLevel.HEADING_2),
        body('El frontend sigue un patrón modular por rol:'),
        bullet('App.js: orquestador principal. Gestiona login/logout, restaura sesión desde localStorage, crea las vistas y llama a _refreshCurrentView() para recargar datos.'),
        bullet('ViewManager.js: controla qué sección HTML está visible (#view-waiter, #view-kitchen, #view-admin), aplica permisos por rol (solo admin ve las tres pestañas) y gestiona modales.'),
        bullet('WaiterView.js: salón de mesas, creación de pedidos, envío a cocina, entrega y cobro.'),
        bullet('KitchenView.js: pantalla KDS con polling periódico (setInterval) para refrescar pedidos pending/preparing.'),
        bullet('AdminView.js: inventario, usuarios, recetas, dashboard diario, cuentas pendientes y reporte de ventas semanales. Su método loadData() hace Promise.all de varios endpoints y luego renderiza cada panel.'),
        body('Cada vista sigue el mismo ciclo: constructor (referencias DOM) → loadData() (peticiones API) → render*() (actualizar HTML). Este patrón es importante para explicar en la presentación cómo se mantiene la UI sincronizada con el backend.'),

        heading('2.5 Organización del backend', HeadingLevel.HEADING_2),
        body('El backend Laravel se estructura en capas bien definidas:'),
        bullet('routes/api.php: catálogo único de endpoints. Laravel antepone automáticamente /api a todas las rutas definidas aquí.'),
        bullet('Controllers (app/Http/Controllers/Api/): reciben la petición HTTP, coordinan la lógica y devuelven JsonResponse. Ejemplos: OrderController, AdminController, AuthController.'),
        bullet('Models (app/Models/): representan tablas Eloquent (Order, User, Ingredient, etc.) con relaciones, constantes de estado y casts de tipos.'),
        bullet('Form Requests (app/Http/Requests/): validan datos de entrada antes de llegar al controlador (LoginRequest, StoreIngredientRequest, etc.).'),
        bullet('API Resources (app/Http/Resources/): transforman modelos a JSON consistente para el frontend (OrderResource, UserResource, etc.).'),
        body('En producción, la conexión a PostgreSQL se configura en .env (variables DB_* o URL de Supabase). Los controladores no conocen el frontend; solo hablan JSON, lo que cumple el principio de separación de responsabilidades.'),

        heading('2.6 Mapa de endpoints por módulo', HeadingLevel.HEADING_2),
        body('A continuación se resumen los grupos de endpoints definidos en routes/api.php. Conocer este mapa es clave para la defensa oral:'),
        table(
          ['Prefijo API', 'Controlador', 'Responsabilidad principal'],
          [
            ['/api/auth/*', 'AuthController', 'Login, registro, sesión, cambio de contraseña'],
            ['/api/inventory/*', 'InventoryController', 'CRUD ingredientes, stock, alertas, restock'],
            ['/api/products/*', 'ProductController', 'Catálogo de platos y categorías'],
            ['/api/recipes/*', 'RecipeController', 'Recetas e ingredientes por producto'],
            ['/api/tables/*', 'TableController', 'Mesas, estados, fusión/unión de mesas (session_id)'],
            ['/api/orders/*', 'OrderController', 'Ciclo de pedidos, KDS, cobros, ingresos diarios'],
            ['/api/reports/*', 'OrderController', 'Reportes daily y weekly (agregación de ventas)'],
            ['/api/admin/*', 'AdminController', 'Gestión de usuarios y estadísticas del dashboard'],
          ]
        ),
        body('Ejemplos concretos del ciclo operativo: POST /api/orders crea la comanda; PATCH /api/orders/{id}/send-kitchen la envía a cocina; GET /api/orders/kds alimenta la pantalla de cocina; PATCH /api/orders/{id}/pay registra el pago; GET /api/reports/weekly entrega el reporte semanal al panel admin.'),

        heading('2.7 Ciclo de vida de una orden (máquina de estados)', HeadingLevel.HEADING_2),
        body('Una orden en F.I.G. atraviesa estados secuenciales almacenados en la columna status de la tabla orders. Comprender este flujo es una pregunta frecuente en evaluaciones:'),
        bullet('pending: recién creada por el mesero. Aún no va a cocina.'),
        bullet('preparing: enviada a cocina (send-kitchen / prepare). Visible en KDS.'),
        bullet('ready: marcada lista por el chef. El mesero puede retirarla.'),
        bullet('delivered: entregada en mesa. Aún no pagada.'),
        bullet('paid: cobrada. Se registra paid_at y se libera la mesa. Solo este estado cuenta para reportes de ingresos.'),
        bullet('cancelled: anulada desde pending; restaura stock en inventario.'),
        body('La venta se reconoce contablemente cuando status=paid y paid_at tiene valor. El reporte semanal filtra exactamente esas órdenes dentro del rango lunes 00:00 – domingo 23:59 de la semana calendario actual, y suma el campo total.'),

        heading('2.8 Flujo técnico del reporte de ventas semanales', HeadingLevel.HEADING_2),
        body('Este entregable ilustra de punta a punta cómo interactúan las capas del sistema:'),
        bullet('1. El administrador abre la vista #view-admin. App.js detecta la vista activa y ejecuta adminView.loadData().'),
        bullet('2. loadData() incluye this.api.getWeeklyRevenue() en un Promise.all junto con inventario, usuarios, stats, etc.'),
        bullet('3. ApiClient hace GET http://localhost:8000/api/reports/weekly.'),
        bullet('4. Laravel enruta a OrderController::weeklyReport(). El método calcula weekStart (lunes) y weekEnd (domingo) con Carbon, consulta órdenes paid con paid_at en ese rango, agrupa por día con Eloquent y rellena los 7 días (incluso con $0 si no hubo ventas).'),
        bullet('5. La respuesta JSON incluye total_revenue, order_count, formatted_revenue y un arreglo days con 7 elementos (day_label: Lun…Dom).'),
        bullet('6. AdminView.renderWeeklySales() escribe los valores en el DOM: totales, rango de fechas y barras proporcionales por día.'),
        body('No se requieren tablas nuevas: el reporte es una consulta de agregación sobre orders existente. Esto demuestra reutilización de datos y ausencia de redundancia en el modelo.'),

        heading('2.9 Modelo de datos (tablas clave)', HeadingLevel.HEADING_2),
        body('Las tablas principales y su rol en el negocio:'),
        bullet('users: credenciales, rol (admin/waiter/chef), estación de cocina.'),
        bullet('restaurant_tables + table_groups: mesas físicas y fusión de mesas por session_id.'),
        bullet('products + categories: carta del restaurante.'),
        bullet('ingredients + recipe_ingredients: insumos y composición de cada plato.'),
        bullet('orders + order_items: comanda y detalle (producto, cantidad, precio unitario snapshot).'),
        bullet('inventory_logs: auditoría de cada movimiento de stock (entrada, salida por pedido).'),
        body('El esquema completo DDL y datos de prueba están en EXPLICACION_Y_DATOS.md y supabase_sanitization.sql.'),

        heading('2.10 Módulos y roles de usuario', HeadingLevel.HEADING_2),
        bullet('Mesero (waiter): salón de mesas, pedidos, envío a cocina, entrega y cobro.'),
        bullet('Chef (chef): pantalla KDS, pedidos pending/preparing, marcar como listo.'),
        bullet('Administrador (admin): usuarios, bodega, recetas, dashboard, cuentas pendientes y reporte de ventas semanales.'),
        body('ViewManager.applyRolePermissions() oculta o muestra navegación según el rol. Un admin puede acceder a las tres vistas; un mesero solo a waiter; un chef solo a kitchen.'),

        heading('2.11 Requerimientos funcionales relevantes', HeadingLevel.HEADING_2),
        bullet('RF-01: Autenticación por rol (admin, waiter, chef)'),
        bullet('RF-02: CRUD de usuarios, productos, ingredientes y recetas'),
        bullet('RF-03: Ciclo de vida de órdenes: pending → preparing → ready → delivered → paid'),
        bullet('RF-04: Descuento atómico de inventario al crear pedidos'),
        bullet('RF-05: Reporte diario de ingresos en panel admin'),
        bullet('RF-06: Reporte semanal de ventas (semana calendario lun-dom) con desglose diario'),

        heading('2.12 Requerimientos no funcionales', HeadingLevel.HEADING_2),
        bullet('RNF-01: API desacoplada del frontend (arquitectura cliente-servidor)'),
        bullet('RNF-02: Respuestas JSON estandarizadas con success/data'),
        bullet('RNF-03: Validación de entrada mediante Form Requests en Laravel'),
        bullet('RNF-04: Contraseñas hasheadas (cast hashed en modelo User)'),
        bullet('RNF-05: Trazabilidad de movimientos de inventario (inventory_logs)'),

        heading('3. Plan de pruebas de software'),
        heading('3.1 Objetivo y alcance', HeadingLevel.HEADING_2),
        body('El plan de pruebas del sistema F.I.G. tiene como objetivo verificar que la solución cumple con la problemática planteada: digitalizar la operación de un restaurante integrando mesero, cocina y administración. El alcance cubre la validación funcional de los tres perfiles de usuario, la integración entre frontend (SPA) y backend (API REST), la coherencia de datos en base de datos, y —como entregable de esta iteración— la correctitud del reporte de ventas semanales expuesto en el panel de administración y su endpoint asociado GET /api/reports/weekly.'),
        body('Las pruebas se organizan en dos niveles complementarios: pruebas de validación manual, ejecutadas por el equipo sobre el sistema en funcionamiento (navegador + API en desarrollo), y pruebas de validación automatizadas, implementadas con PHPUnit sobre el backend Laravel. Ambas capas están alineadas a la misma problemática y se documentan en la tabla de casos de prueba (sección 3.6).'),

        heading('3.2 Estrategia y tipología de pruebas', HeadingLevel.HEADING_2),
        body('La estrategia adoptada sigue el enfoque de pirámide de pruebas adaptado al contexto del proyecto. En la base se ubican las pruebas automatizadas de API (Feature tests), que permiten repetir escenarios de forma determinista y detectar regresiones en la lógica de negocio. En el nivel superior se aplican pruebas manuales de validación por rol, que comprueban la experiencia de usuario, la navegación entre vistas y la coherencia visual de los datos presentados en el SPA.'),
        body('Se identifican los siguientes tipos de prueba:'),
        bullet('Pruebas de validación manual (caja negra): el evaluador interactúa con el frontend en http://localhost:5173, autenticándose con usuarios de distintos roles y verificando flujos completos (mesas → pedido → cocina → cobro → reporte).'),
        bullet('Pruebas Feature automatizadas (caja gris): PHPUnit simula peticiones HTTP al backend (php artisan test) sin navegador, validando respuestas JSON, códigos de estado y reglas de agregación de datos.'),
        bullet('Pruebas de integración funcional: recorridos que atraviesan varios módulos (por ejemplo, crear y pagar una orden, luego verificar que el reporte semanal refleja el ingreso).'),
        bullet('Revisión de buenas prácticas y calidad: uso de Form Requests para validación, API Resources para serialización, factories para datos de prueba y separación de responsabilidades entre vistas JS y controladores PHP.'),
        bullet('Revisión de seguridad básica: autenticación por rol, hashing de contraseñas, exclusión de órdenes no pagadas en reportes de ingresos.'),

        heading('3.3 Herramientas, librerías y entorno de ejecución', HeadingLevel.HEADING_2),
        body('Pruebas automatizadas (backend):'),
        body('El framework de pruebas principal es PHPUnit 12.x, integrado en Laravel 13 mediante el comando php artisan test (que internamente invoca vendor/bin/phpunit). La configuración reside en backend-laravel/phpunit.xml y define dos suites: Unit (tests/Unit) y Feature (tests/Feature). Para las pruebas del reporte semanal se utiliza la suite Feature, ya que el endpoint bajo prueba requiere interacción HTTP real y acceso a base de datos.'),
        body('Librerías y componentes del ecosistema de pruebas (declaradas en composer.json, sección require-dev):'),
        bullet('phpunit/phpunit (^12.5): motor de ejecución, aserciones y reporte de resultados.'),
        bullet('laravel/framework (^13.8): incluye Illuminate\\Foundation\\Testing con traits MakesHttpRequests, RefreshDatabase e InteractsWithDatabase para simular peticiones y gestionar BD de prueba.'),
        bullet('fakerphp/faker (^1.23): generación de datos ficticios en factories (nombres, montos, etc.).'),
        bullet('mockery/mockery (^1.6): creación de mocks para aislar dependencias cuando se requiera (disponible para pruebas futuras).'),
        bullet('nunomaduro/collision (^8.6): salida formateada de errores en consola durante la ejecución.'),
        bullet('Carbon (incluido en Laravel): fijación de fecha/hora con Carbon::setTestNow() para pruebas deterministas del reporte semanal.'),
        body('Base de datos de pruebas automatizadas: SQLite en memoria (:memory:), configurada en phpunit.xml mediante las variables DB_CONNECTION=sqlite y DB_DATABASE=:memory:. Antes de cada test, el trait RefreshDatabase ejecuta las migraciones Laravel (users, orders, etc.) y deja la BD en estado limpio. En producción el sistema usa PostgreSQL (Supabase); la lógica de agregación del reporte semanal fue refactorizada con Eloquent para ser portable entre ambos motores.'),
        body('Pruebas manuales (frontend + integración):'),
        body('El frontend es una SPA servida con npm (puerto 5173 por defecto). No existe framework de tests automatizados en el frontend (sin Jest/Vitest); la validación visual y de flujo se realiza manualmente en navegador. El backend debe estar levantado con php artisan serve (puerto 8000). Las peticiones del SPA se canalizan a través de ApiClient.js. Para pruebas integrales se utiliza PostgreSQL en Supabase con el esquema y datos de prueba documentados en EXPLICACION_Y_DATOS.md.'),
        body('Requisitos del entorno de ejecución de tests automatizados:'),
        bullet('PHP ^8.3 con extensiones: mbstring, pdo_sqlite, dom, json, libxml, tokenizer, xml, xmlwriter.'),
        bullet('Composer instalado (composer install en backend-laravel/).'),
        bullet('Comando de ejecución: cd backend-laravel && php artisan test --filter=WeeklyReportTest'),
        bullet('Resultado esperado documentado: 8 tests passed, 69 assertions.'),

        heading('3.4 Estructura de las pruebas automatizadas', HeadingLevel.HEADING_2),
        body('El archivo principal de pruebas del reporte semanal es backend-laravel/tests/Feature/WeeklyReportTest.php. Cada método test_* representa un caso de prueba independiente. El ciclo de vida de cada test sigue este patrón: (1) setUp() fija la fecha actual con Carbon::setTestNow("2025-06-11") para que la semana calendario sea predecible (lun 9 jun – dom 15 jun 2025); (2) se crean usuarios y órdenes mediante User::factory() y Order::factory() con estados paid(), pending() o delivered(); (3) se invoca GET /api/reports/weekly con $this->getJson(); (4) se verifican código HTTP, estructura JSON y valores numéricos con aserciones PHPUnit; (5) tearDown() restaura la fecha del sistema.'),
        body('Los datos de prueba se generan con factories ubicadas en database/factories/. OrderFactory define estados semánticos: paid() asigna status=paid y paid_at; pending() y delivered() representan órdenes no cobradas. Esto permite construir escenarios controlados sin depender de datos reales de Supabase.'),

        heading('3.5 Criterios de aceptación del plan de pruebas', HeadingLevel.HEADING_2),
        body('Un caso de prueba manual se considera aprobado cuando el comportamiento observado en pantalla coincide con el resultado esperado definido en la tabla y queda respaldado con evidencia (captura de pantalla o nota de ejecución). Un caso automatizado se considera aprobado cuando PHPUnit reporta PASS y todas las aserciones del método se cumplen. El plan completo se considera ejecutado cuando: (a) los 8 tests automatizados del reporte semanal están en verde; (b) los casos manuales PT-01 a PT-12 han sido recorridos y documentados por el equipo; (c) las evidencias están adjuntas en la sección 4.4 de este informe.'),
        placeholder('Completar: fecha y visto bueno del docente guía sobre este plan de pruebas.'),

        heading('3.6 Tabla de casos de prueba', HeadingLevel.HEADING_2),
        body('La siguiente tabla detalla las acciones a realizar, las funcionalidades a comprobar y el resultado esperado para cada caso. Los identificadores PT-13 a PT-16 corresponden a pruebas automatizadas ya implementadas; PT-01 a PT-12 requieren ejecución manual y evidencia por parte del equipo.'),
        table(['ID', 'Componente', 'Acción / Funcionalidad', 'Resultado esperado', 'Estado'], planPruebas),

        heading('4. Aplicación de pruebas de validación'),
        heading('4.1 Base de datos de pruebas', HeadingLevel.HEADING_2),
        body('Es importante distinguir dos entornos de base de datos en este proyecto, ya que cumplen propósitos distintos:'),
        body('Entorno automatizado (PHPUnit): usa SQLite en memoria para velocidad y aislamiento. Cada test parte de una BD vacía gracias a RefreshDatabase. La migración mínima de orders permite probar el endpoint semanal sin levantar Supabase. Este entorno valida la lógica del código, no la infraestructura cloud.'),
        body('Entorno manual e integración (demostración): usa PostgreSQL en Supabase con el esquema completo (10+ tablas, relaciones, constraints). Los datos de prueba del archivo EXPLICACION_Y_DATOS.md permiten demostrar mesas ocupadas, pedidos en cocina e ingresos del día. La demostración oral al profesor debe usar este entorno.'),

        heading('4.2 Pruebas automatizadas ejecutadas', HeadingLevel.HEADING_2),
        body('Comando: php artisan test --filter=WeeklyReportTest (desde backend-laravel/)'),
        body('Resultado registrado al generar este informe: 8 tests, 8 passed, 69 assertions.'),
        table(['Caso de prueba', 'Descripción', 'Resultado'], pruebasAutomatizadas),

        heading('4.3 Validación por componente', HeadingLevel.HEADING_2),
        table(
          ['Componente', 'Pruebas aplicadas', 'Funcionalidad', 'Buenas prácticas', 'Calidad', 'Seguridad'],
          [
            ['Backend API /api/reports/weekly', '8 tests Feature', 'OK', 'Query portable Eloquent', 'Suite automatizada', 'Solo datos paid'],
            ['Frontend AdminView', 'Manual planificada PT-12', PLACEHOLDER('resultado'), 'Patrón loadData/render', PLACEHOLDER('resultado'), 'Rol admin en UI'],
            ['Módulo Mesero', 'Manual PT-03 a PT-06', PLACEHOLDER('resultado'), 'ApiClient centralizado', PLACEHOLDER('resultado'), PLACEHOLDER('resultado')],
            ['Módulo Cocina KDS', 'Manual PT-07, PT-08', PLACEHOLDER('resultado'), 'Polling refresh', PLACEHOLDER('resultado'), PLACEHOLDER('resultado')],
            ['Auth', 'Manual PT-01, PT-02', PLACEHOLDER('resultado'), 'Form Requests', PLACEHOLDER('resultado'), 'Password hashed'],
            ['Inventario', 'Manual PT-10', PLACEHOLDER('resultado'), 'InventoryLog auditoría', PLACEHOLDER('resultado'), PLACEHOLDER('resultado')],
          ]
        ),

        heading('4.4 Evidencias', HeadingLevel.HEADING_2),
        placeholder('Insertar capturas de pantalla: panel Ventas Semanales, salida de php artisan test, vistas mesero/cocina/admin, tablero Kanban.'),
        bullet('Evidencia texto — salida tests: 8 tests passed, 69 assertions'),
        bullet('Archivo de pruebas: backend-laravel/tests/Feature/WeeklyReportTest.php'),
        bullet('Endpoint probado: GET /api/reports/weekly'),

        heading('5. Mejoras al producto'),
        body('Las mejoras listadas a continuación surgieron directamente de la ejecución de pruebas o de la revisión de calidad del código. Cada fila vincula un hallazgo concreto con la acción tomada y el estándar de la industria que se busca cumplir (usabilidad, seguridad, completitud, corrección, pertinencia).'),
        table(['Tipo', 'Área', 'Hallazgo', 'Mejora aplicada', 'Estándar'], mejoras),

        heading('6. Documentación y configuración'),
        body('La documentación del proyecto soporta tanto el despliegue como la evaluación académica:'),
        bullet('EXPLICACION_Y_DATOS.md — arquitectura, flujos, DDL y datos de prueba'),
        bullet('docs/CONFIGURACION_SERVIDOR_PRODUCCION.md — configuración de servidor'),
        bullet('docs/ANEXO_Procedimiento_Respaldo_BD.md — respaldos diarios/semanales'),
        bullet('backend-laravel/.env.example — variables de entorno (sin secretos)'),
        bullet('supabase_sanitization.sql — saneamiento estructural de BD'),
        placeholder('Adjuntar o referenciar copias de configuración de entregas anteriores si el docente las solicitó.'),

        heading('7. Aceptación y tablero Kanban'),
        heading('7.1 Documento de aceptación', HeadingLevel.HEADING_2),
        placeholder('Documento de aceptación de cliente (si aplica).'),
        placeholder('Documento de aceptación / visto bueno del docente guía sobre plan de pruebas.'),

        heading('7.2 Tablero Kanban', HeadingLevel.HEADING_2),
        placeholder('Insertar estado actualizado del tablero Kanban con historias de usuario y criterios de aceptación para la presentación oral.'),

        heading('8. Conclusión'),
        body('El proyecto F.I.G. entrega una solución funcional para la operación gastronómica integrada, con backend Laravel, frontend SPA y base de datos PostgreSQL. En esta iteración se incorporó el reporte de ventas semanales (API + panel admin) y una suite de 8 pruebas automatizadas que validan la lógica de agregación semanal. Las pruebas manuales por rol quedan documentadas en el plan y deben completarse con evidencias visuales para la evaluación de presentación.'),
        placeholder('Completar con reflexión final del equipo.'),

        heading('9. Lecciones aprendidas'),
        bullet('Separar la lógica de agregación de SQL específico de motor facilita pruebas con SQLite y despliegue en PostgreSQL.'),
        bullet('Las factories y migraciones mínimas permiten probar endpoints sin replicar todo el esquema de Supabase.'),
        bullet('Un plan de pruebas alineado a roles (mesero, chef, admin) refleja mejor la problemática real del restaurante.'),
        bullet('La validación automatizada del reporte semanal reduce regresiones en cálculos de ingresos.'),
        placeholder('Completar con lecciones personales del equipo (metodología, trabajo en equipo, gestión del tiempo).'),

        heading('10. Anexos'),
        bullet('Anexo A: backend-laravel/tests/Feature/WeeklyReportTest.php'),
        bullet('Anexo B: backend-laravel/app/Http/Controllers/Api/OrderController.php — método weeklyReport()'),
        bullet('Anexo C: frontend/js/modules/AdminView.js — renderWeeklySales()'),
        bullet('Anexo D: frontend/js/api/ApiClient.js — capa de comunicación HTTP'),
        bullet('Anexo E: backend-laravel/routes/api.php — mapa de endpoints'),
        bullet('Anexo F: EXPLICACION_Y_DATOS.md'),
        placeholder('Anexo G: Capturas de pantalla y evidencias de pruebas manuales'),

        heading('11. Guía de estudio para la presentación oral'),
        body('Esta sección resume argumentos y respuestas preparadas para preguntas frecuentes del docente durante la defensa. Se recomienda repasarla junto con una demostración en vivo del sistema.'),

        heading('11.1 Preguntas sobre arquitectura', HeadingLevel.HEADING_2),
        bullet('¿Por qué separaron frontend y backend? — Para desacoplar la interfaz de la lógica de negocio, permitir escalabilidad independiente y reutilizar la API desde otros clientes.'),
        bullet('¿Cómo se comunican? — HTTP/JSON. El frontend usa fetch vía ApiClient; el backend responde con JsonResponse desde controladores Laravel.'),
        bullet('¿Por qué UUID y no enteros autoincrementales? — Facilita integración distribuida con Supabase y evita colisiones al sincronizar datos entre entornos.'),

        heading('11.2 Preguntas sobre el flujo operativo', HeadingLevel.HEADING_2),
        bullet('¿Qué pasa al crear un pedido? — Se valida stock según recipe_ingredients, se descuenta inventario, se registra en inventory_logs y se crean orders + order_items.'),
        bullet('¿Cuándo cuenta una venta en el reporte? — Solo cuando la orden pasa a paid y se guarda paid_at. Pedidos delivered sin cobrar no suman.'),
        bullet('¿Cómo funciona la cocina? — KitchenView hace polling a GET /api/orders/kds y muestra pedidos pending/preparing con semáforo de tiempo.'),

        heading('11.3 Preguntas sobre el reporte semanal', HeadingLevel.HEADING_2),
        bullet('¿Necesitaron tablas nuevas? — No. Se agregan sobre orders (status, paid_at, total).'),
        bullet('¿Qué define la semana? — Semana calendario actual: lunes 00:00 a domingo 23:59 (Carbon::startOfWeek(MONDAY)).'),
        bullet('¿Cómo lo probaron? — 8 tests Feature con SQLite, factories y Carbon::setTestNow() para fecha fija.'),
        bullet('¿Por qué refactorizaron la query? — El SQL DATE() de PostgreSQL no funciona en SQLite; Eloquent agrupa en PHP y es portable.'),

        heading('11.4 Preguntas sobre pruebas y calidad', HeadingLevel.HEADING_2),
        bullet('¿Qué es una prueba Feature vs Unit? — Feature prueba HTTP + BD (endpoint completo); Unit probaría una función aislada sin framework.'),
        bullet('¿Por qué no hay tests de frontend? — El SPA no tiene Jest/Vitest configurado; la validación visual es manual según plan PT-01 a PT-12.'),
        bullet('¿Qué validan los Form Requests? — Tipos, campos obligatorios y reglas antes de ejecutar lógica en controladores (seguridad y corrección).'),

        heading('11.5 Preguntas sobre seguridad', HeadingLevel.HEADING_2),
        bullet('¿Cómo se protegen las contraseñas? — Cast hashed en modelo User; Laravel usa bcrypt.'),
        bullet('¿Hay control por rol? — Sí en UI (ViewManager) y los endpoints deben usarse según el flujo de cada perfil.'),
        bullet('¿Los reportes exponen datos no pagados? — No. weeklyReport filtra status=paid y paid_at no nulo.'),

        heading('11.6 Secuencia sugerida para la demo oral', HeadingLevel.HEADING_2),
        bullet('1. Login como mesero → mostrar mesas → crear pedido → enviar a cocina.'),
        bullet('2. Login como chef → KDS → marcar listo.'),
        bullet('3. Volver como mesero → entregar → cobrar.'),
        bullet('4. Login como admin → panel Ventas Semanales + ingresos del día.'),
        bullet('5. Mostrar salida de php artisan test --filter=WeeklyReportTest (8 passed).'),
        bullet('6. Comparar criterios de aceptación del Kanban con lo demostrado.'),
        placeholder('Completar: notas personales del equipo para la defensa.'),
      ],
    },
  ],
});

const buffer = await Packer.toBuffer(doc);
fs.writeFileSync(outputPath, buffer);
console.log('Generado:', outputPath);
