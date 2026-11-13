# PLAN para terminar el proyecto

Cómo se usa: en cada sesión haz SOLO la primera parte que no esté marcada [x]. Al terminarla, márcala [x] aquí, haz commit y DETENTE.

## Reglas de trabajo (para ahorrar contexto)
- Copia los patrones que ya existen (módulo Pedidos y componentes de web/src/app/shared). No releas archivos que no vas a tocar.
- Mockups: mira SOLO los de la parte en curso, una vez. Los círculos rojos numerados son anotaciones y no van en la app. Aplica "Ajustes al modelo" de docs/interfaces/README.md.
- NO abras el navegador ni tomes capturas. Verifica con php artisan test, ng build (web) e ionic build (movil).
- Responde corto. El resumen final de cada parte tiene máximo 15 líneas: qué hiciste, cómo probarlo (usuario y pasos) y qué quedó pendiente.

## Reglas del proyecto
- Usuarios de prueba: admin@, operador@, comercio@, comercio2@ y repartidor@logistica.test, contraseña "password".
- Base de Neon compartida: NUNCA migrate:fresh, reset, refresh, rollback ni db:wipe. Las migraciones nuevas y los seeders se corren solo con mi confirmación. No modifiques migraciones ya ejecutadas.
- Toda regla de negocio va en el backend, con prueba Pest (casos de error y permisos por rol incluidos).
- Errores en el formato {"error": {"codigo", "mensaje", "campos"}}, con mensajes en español.
- Botones principales en #0B6E6E. El naranja #F2A33A solo va en el logo y en la franja del menú activo.
- Un commit por tarea. No hagas git push.

---

## [x] Parte 0 — CLAUDE.md corto
Reduce CLAUDE.md (raíz) a máximo 80 líneas y backend/CLAUDE.md a máximo 60, sin perder:
- las reglas de seguridad de la base;
- la estructura de carpetas;
- el formato de errores;
- cómo hacer un CRUD;
- la Guía de interfaz (componentes y mockups).
Lo que sobre se mueve a docs/guia-detallada.md.

## [x] Parte A — Despacho (mockups INT-08, INT-09, INT-10, INT-11)
1. Máquina de estados en UN solo lugar.
   - Transiciones permitidas:
     - creado→asignado
     - creado→cancelado
     - asignado→asignado (reasignar)
     - asignado→recogido
     - asignado→cancelado
     - recogido→en_ruta
     - en_ruta→entregado
     - en_ruta→no_entregado
   - Entregado, no_entregado y cancelado son estados finales.
   - Cualquier otra transición se rechaza con un error claro.
   - Pest prueba cada transición permitida y las no permitidas.
2. Asignar y reasignar (administrador u operador).
   - El repartidor debe estar activo y disponible, ser de la misma zona del pedido y estar por debajo de max_pedidos_activos (cuentan los pedidos en asignado, recogido y en_ruta).
   - No se puede reasignar al mismo repartidor.
   - Todo se hace en una transacción con bloqueo.
   - Se registra en historial_pedido (tipo asignacion, canal tomado de X-Canal) con el repartidor en el detalle.
   - Si se rechaza, se informa el motivo: desactivado, no disponible, fuera de zona o máximo alcanzado.
3. Ventana INT-09.
   - Pestaña "Propuesta": los elegibles, de menor a mayor carga.
   - Pestaña "Elegir manualmente": todos los activos; los no elegibles en gris con el motivo en rojo.
   - Cada repartidor muestra su carga como "1 / 3".
   - Si nadie es elegible: "No hay repartidores disponibles para la zona del pedido."
4. Cancelar (INT-10, administrador u operador).
   - Solo en creado o asignado.
   - Motivo obligatorio ("Escribe el motivo de la cancelación."), que queda en el historial.
5. Disponibilidad.
   - Endpoint para que el repartidor cambie la suya; el operador puede cambiar la de cualquiera.
   - Pasar a no disponible no le quita los pedidos que ya tiene.
   - Página Repartidores (INT-11): correo, zona, interruptor de disponibilidad y "activos / máximo".
6. Detalle del pedido (INT-08): botones Asignar (en creado), Reasignar (en asignado) y Cancelar (en creado o asignado). El comercio no los ve.

## [x] Parte B — Panel y datos de demostración (mockup INT-04)
1. Panel para administrador y operador, con datos reales.
   - Filtros: fechas de creación, zona, comercio y estado.
   - Indicadores:
     - pedidos por estado, con badges;
     - % de entregas exitosas = entregados / (entregados + no_entregados) × 100;
     - tiempo promedio en minutos entre la creación y la entrega (la hora de entrega sale del historial);
     - incidencias por tipo, por zona y por repartidor, en barras horizontales.
   - Lista de pedidos con ⚠ si tienen una incidencia abierta.
   - "Sin datos" cuando no hay base de cálculo.
   - Los cálculos se hacen con consultas agregadas en el backend.
2. Amplía el seeder de demostración (idempotente, sin borrar nada):
   - repartidor2@ a repartidor5@logistica.test (contraseña "password") en distintas zonas y con distinta disponibilidad;
   - unos 15 pedidos en estados variados, con historial y horas coherentes, de los cuales 3 quedan asignados a repartidor@logistica.test para probar el móvil;
   - incidencias en los pedidos no entregados;
   - ninguna evidencia sin archivo.
   Pide confirmación antes de correrlo.

## [x] Parte C — App móvil del repartidor (mockups 00b, INT-01, INT-15 a INT-21, INT-22)
1. Tema de los mockups en las variables de Ionic.
   - Fuente Inter.
   - Barra superior #0B6E6E con texto blanco, tarjetas blancas.
   - Badges con los mismos colores que la web.
   - Botones de mínimo 48 × 48 dp.
   - Toasts de éxito y error, y aviso "Sin conexión" con Reintentar.
2. Base de la app.
   - Login como INT-01.
   - Pestañas "Mis pedidos" y "Perfil".
   - Todas las peticiones envían el encabezado X-Canal: movil.
3. Mis pedidos (INT-15).
   - Tarjetas de los pedidos en asignado, recogido o en_ruta.
   - Deslizar para actualizar.
   - Estado vacío "No tienes pedidos asignados."
4. Detalle y cambio de estado (INT-16, INT-17).
   - Solo se muestran las acciones permitidas, en 3 toques o menos desde la lista.
   - Entregado exige al menos una evidencia ("Falta la evidencia…").
   - No entregado exige evidencia, una incidencia y un motivo escrito, con su lista de requisitos.
   - Solo sobre pedidos propios: el backend lo verifica.
5. Evidencia (INT-18).
   - Cámara o galería (@capacitor/camera); tipo foto_entrega o comprobante.
   - Ubicación con @capacitor/geolocation; si no hay permiso, se guarda sin coordenadas.
   - Solo en recogido o en_ruta.
   - JPG o PNG de máximo 5 MB, validado en el backend.
   - Se guarda en el disco "public" (storage:link) y en la tabla solo la ruta.
6. Incidencia (INT-19 móvil).
   - Tipo (solo los activos), descripción obligatoria y foto opcional.
   - Solo en recogido o en_ruta.
   - Queda en estado "abierta".
7. Pestañas de evidencias e incidencias del pedido, de solo lectura (INT-20). No se muestra nota_comercio.
8. Perfil (INT-21): correo, rol, zona, interruptor de disponibilidad y Cerrar sesión.

## [ ] Parte D — Incidencias y asistente de IA (mockups INT-12, INT-13, INT-14, INT-19 web)
1. Lista de incidencias.
   - Filtros por estado, tipo, prioridad y número de pedido.
   - Administrador y operador ven todas; el comercio solo las de sus pedidos.
2. Detalle de la incidencia.
   - Asignar prioridad.
   - Cambiar estado de abierta a en_revision y de en_revision a resuelta; para resolver, la resolución es obligatoria.
   - nota_comercio: una sola nota, editable y no vacía.
   - El operador puede registrar una incidencia desde el detalle del pedido (ventana INT-19).
3. Asistente de IA (administrador y operador).
   - Endpoint que recibe preguntas sobre pedidos e incidencias.
   - Consulta solo los datos que el usuario puede ver.
   - ANONIMIZA antes de enviar: sin nombre, cédula, teléfono, correo ni dirección del cliente; usa el número de pedido.
   - Llama a la IA desde el backend detrás de una interfaz (ClienteIA), con un límite de 30 s:
     - Proveedor por defecto: Google Gemini, modelo Flash del plan gratuito, con la clave en IA_API_KEY.
     - Segunda implementación: Groq (API compatible con OpenAI).
     - Se cambia con IA_PROVEEDOR=gemini|groq e IA_MODELO en el .env, sin tocar código.
     - Si la API responde por límite de peticiones (429), se trata como falla: "El asistente no está disponible".
   - Nunca modifica datos.
   - Si la pregunta no es sobre la operación, responde que solo atiende consultas de pedidos e incidencias.
   - Si no hay datos: "No se encontró información sobre esa consulta".
   - Si falla o tarda más de 30 s: "El asistente no está disponible", con Reintentar.
   - Cada consulta se registra en consultas_asistente como respondida o fallida.
   - Las pruebas Pest usan un cliente simulado.
4. Página tipo chat (INT-14) con "Nueva conversación", y botón "Resumir con el asistente" en el detalle de la incidencia.
5. Agrega IA_PROVEEDOR=gemini, IA_MODELO= e IA_API_KEY= (vacía) a .env.example, y explica en el README cómo sacar la clave gratuita en Google AI Studio y en Groq.

## [ ] Parte E — Cierre para el equipo
1. README: cómo instalar y correr backend, web y movil, y los usuarios de prueba.
2. docs/PENDIENTES.md con:
   - errores conocidos;
   - diferencias con los mockups;
   - mejoras pendientes, entre ellas un buscador en el selector de cliente y el reintento del número de pedido;
   - una propuesta de reparto de módulos entre 5 personas, para que cada uno pruebe y ajuste el suyo.
3. Verificación final: php artisan test, ng build e ionic build sin errores.
