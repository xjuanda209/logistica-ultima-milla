# Interfaces — Última Milla

Mockups oficiales del equipo (versión resumida del documento "Documentación de Interfaces").
Las imágenes están en `mockups/`. **Cópialas en aspecto (colores, distribución, textos y mensajes)**, pero cuando
choquen con el modelo de datos manda el modelo: ver "Ajustes al modelo" abajo.

## Estilo visual (sacado de los mockups)

| Elemento | Valor |
|---|---|
| Primario (botones, enlaces, barras de gráficos) | `#0B6E6E` |
| Barra lateral (fondo) | `#14313B`; ítem activo `#29434C` con franja izquierda naranja |
| Acento (franja del menú activo, "Milla" del logo) | `#F2A33A` |
| Fondo de la página | `#F3F6F7`; tarjetas blancas, borde gris claro, esquinas redondeadas |
| Fuente | Inter |
| Logo | texto "Última" blanco + "Milla" naranja |
| Barra superior | título de la página a la izquierda; a la derecha correo, badge del rol y botón "Cerrar sesión" |
| Encabezados de tabla | MAYÚSCULAS, gris, pequeños |
| Marcadores rojos numerados | Son anotaciones del documento: **NO** van en la app |

**Badges de estado del pedido** (fondo suave + texto del mismo tono, siempre con texto, nunca solo color):
creado gris (`#E8EDF1`), asignado azul (`#E4EEF9`), recogido morado (`#EDE7F8`), en_ruta ámbar (`#FCF0D9`),
entregado verde (`#E3F4EA`), no_entregado rojo (`#FDE8E8`), cancelado gris oscuro (`#E2E5E8`).
Prioridad: baja gris, media ámbar, alta roja. Incidencia: abierta roja, en_revision ámbar, resuelta verde.
Incidencia abierta en una lista de pedidos: ícono ⚠ rojo.

**Móvil**: barra superior `#0B6E6E` con flecha atrás, tarjetas blancas sobre fondo gris claro, botones grandes
(mínimo 48 × 48 dp) que ocupan todo el ancho; "entregado" verde, "no entregado" rojo.

## Pantallas

| ID | Mockup | Canal | Rol(es) | Módulo |
|---|---|---|---|---|
| — | `00a-navegacion-web.png`, `00b-navegacion-movil.png` | — | — | Diseño |
| INT-01 | `INT-01-login.png` | Web y móvil | Todos | Diseño |
| INT-02 | `INT-02-usuarios.png` | Web | Administrador | Diseño |
| INT-03 | `INT-03-parametrizacion.png` | Web | Administrador | Diseño |
| INT-22 | `INT-22-mensajes.png` | Web y móvil | Todos | Diseño |
| INT-05 | `INT-05-clientes.png` | Web | Admin, operador, comercio | 2 Pedidos |
| INT-06 | `INT-06-pedidos-lista.png` | Web | Admin, operador, comercio | 2 Pedidos |
| INT-07 | `INT-07-pedido-formulario.png` | Web | Admin, operador, comercio | 2 Pedidos |
| INT-08 | `INT-08-pedido-detalle.png` | Web | Admin, operador, comercio | 2 Pedidos (datos) y 3 Despacho (botones) |
| INT-04 | `INT-04-panel.png` | Web | Admin, operador | 3 Despacho |
| INT-09 | `INT-09-asignar-repartidor.png` | Web (modal) | Admin, operador | 3 Despacho |
| INT-10 | `INT-10-cancelar-pedido.png` | Web (modal) | Admin, operador | 3 Despacho |
| INT-11 | `INT-11-repartidores.png` | Web | Operador | 3 Despacho |
| INT-15 | `INT-15-movil-mis-pedidos.png` | Móvil | Repartidor | 4 Móvil |
| INT-16 | `INT-16-movil-detalle-pedido.png` | Móvil | Repartidor | 4 Móvil |
| INT-17 | `INT-17-movil-no-entregado.png` | Móvil | Repartidor | 4 Móvil |
| INT-18 | `INT-18-movil-evidencia.png` | Móvil | Repartidor | 4 Móvil |
| INT-19 | `INT-19-registrar-incidencia.png` | Móvil y web (modal) | Repartidor; operador | 4 Móvil (móvil) y 5 Incidencias (web) |
| INT-20 | `INT-20-movil-evidencias-incidencias.png` | Móvil | Repartidor | 4 Móvil |
| INT-21 | `INT-21-movil-perfil.png` | Móvil | Repartidor | 4 Móvil |
| INT-12 | `INT-12-incidencias-lista.png` | Web | Admin, operador, comercio | 5 Incidencias |
| INT-13 | `INT-13-incidencia-detalle.png` | Web | Admin, operador (gestionan); comercio (consulta) | 5 Incidencias |
| INT-14 | `INT-14-asistente-ia.png` | Web | Admin, operador | 5 Incidencias |

**Menú lateral por rol** (solo ve lo suyo):
- Administrador: Panel, Usuarios, Parametrización, Pedidos, Clientes, Incidencias, Asistente IA.
- Operador: Panel, Pedidos, Clientes, Repartidores, Incidencias, Asistente IA.
- Comercio: Pedidos, Clientes, Incidencias.
- Pantalla de inicio después del login: administrador y operador → Panel; comercio → Pedidos; repartidor → Mis pedidos (móvil).

## Notas por pantalla (lo que la imagen no dice)

- **INT-01** Error único "Correo o contraseña incorrectos." (también si la cuenta está desactivada). Aviso de sesión vencida. Nota: "Si olvidaste la contraseña, pídele al administrador que la restablezca".
- **INT-04** Filtros desde, hasta, zona, comercio, estado + "Aplicar". Pedidos por estado, % entregas exitosas = entregados ÷ (entregados + no entregados) × 100, tiempo promedio en minutos, incidencias por tipo, zona y repartidor (barras), lista de pedidos con ⚠. "Sin datos" si no hay base de cálculo.
- **INT-06** Más reciente primero. Búsqueda por número ("Pedido no encontrado" si no existe o no es suyo), rango de fechas y estado. Admin y operador ven además la columna Comercio. No hay opción de eliminar.
- **INT-07** Al guardar va al detalle (INT-08). Errores: "Debe ser mayor que 0." y "La franja no puede empezar en el pasado." Aviso: solo se edita en estado «Creado».
- **INT-09** Pestañas "Propuesta" y "Elegir manualmente". La propuesta ordena de menor a mayor carga; los no elegibles se muestran en gris con el motivo en rojo (fuera de zona, no disponible, alcanzó el máximo, cuenta desactivada). Columna "pedidos activos" como `1 / 3`. Si no hay nadie: "No hay repartidores disponibles para la zona del pedido."
- **INT-10** Motivo obligatorio ("Escribe el motivo de la cancelación."). Solo en «Creado» o «Asignado».
- **INT-11** Interruptor de disponibilidad por repartidor; aviso: un repartidor no disponible conserva sus pedidos pero no recibe nuevos.
- **INT-13** Prioridad, botones Abierta → En revisión → Resuelta, resolución obligatoria para resolver, nota para el comercio, botón "Resumir con el asistente".
- **INT-14** Chat. Aviso "El asistente solo informa, no modifica datos". Error "El asistente no está disponible" con "Reintentar". Botón "Nueva conversación".
- **INT-15** Pedidos activos del repartidor en tarjetas, disponibilidad en el encabezado, "actualizado hace…", estado vacío "No tienes pedidos asignados."
- **INT-16** Solo aparecen los botones permitidos por el estado (máximo 3 toques desde la lista). Error "Falta la evidencia: registra al menos una antes de marcarlo como entregado."
- **INT-17** Lista de requisitos con ✔/✖ (evidencia, incidencia, motivo); el botón queda deshabilitado hasta cumplirlos.
- **INT-18** Tipo (foto de entrega / comprobante), "Tomar foto" o "Galería", vista previa con nombre y tamaño, "Ubicación capturada" o "Sin ubicación". JPG o PNG, máximo 5 MB.
- **INT-20** Pestañas Evidencias / Incidencias, solo lectura; el repartidor no ve la nota para el comercio.
- **INT-22** Toast verde de éxito, rojo de error con el motivo, aviso "Acceso denegado" y franja "Sin conexión" con "Reintentar". Todo en español y sin textos técnicos.

## Ajustes al modelo (los mockups se hicieron antes de simplificar la base de datos)

Donde el mockup y el modelo no coinciden, **se respeta el modelo de 12 tablas** y la pantalla se adapta así:

| Mockup | En nuestra app |
|---|---|
| INT-05: un cliente con **varias direcciones** que se activan y desactivan | Cada cliente tiene **una** dirección (dirección, barrio, indicaciones, zona) dentro del mismo formulario "Datos del cliente". No hay tabla "Direcciones de entrega" ni botón "Nueva dirección". |
| INT-07: **selector de dirección** | No existe. Al elegir el cliente se muestra su dirección como vista previa (solo lectura); al guardar se copia al pedido. |
| INT-03: zona con **varios repartidores que la cubren** / repartidor con varias zonas | Cada repartidor tiene **una** zona (se elige en Usuarios). En la zona se pueden mostrar como chips los repartidores que tienen esa zona, solo para consulta. Editar zona = nombre y descripción. |
| INT-03: bloque **"Estados del pedido"** con nombre editable | Se quita. Los nombres de los estados son fijos y se definen en un solo lugar del frontend. |
| INT-03: casillas **"Reglas de asignación"** (zona, disponibilidad) | Se quitan; esas reglas siempre aplican. El bloque queda solo con "Máximo de pedidos activos por repartidor (1 a 20)". |
| INT-02 / INT-08: **datos de auditoría** (quién modificó y cuándo) | Solo se muestra fecha de creación y de última modificación; en pedidos también "creado por". No se guarda quién modificó. |
| INT-13: **historial de notas** para el comercio | Una sola nota (`nota_comercio`) que se puede editar; se muestra la vigente. |
| INT-09 / INT-11 / INT-21: columna **"Zonas"** | "Zona" (una sola). |
| INT-02: formulario sin campo nombre | Se mantiene el campo **nombre** (existe en la tabla) y, si el rol es repartidor, la **zona**. |
