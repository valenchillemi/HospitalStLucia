# Informe final de la prueba de concepto Blue Green

**Proyecto:** Clínica Santa Lucía, portal hospitalario simulado

**Asignatura:** Ingeniería de Software, Grupo 03

**Integrantes según la segunda entrega:** Germán Bariguian, Valentina Chillemi, Ignacio Manuel Camporro y Joaquín Mutolo Terzano

**Fecha del ensayo:** 25 de octubre de 2026 en Argentina; los registros UTC muestran el 26 de octubre

**Estado:** borrador técnico para integrar y revisar con el equipo

## Resumen ejecutivo

La prueba de concepto (PoC) implementó un despliegue Blue Green automatizado para una aplicación hospitalaria ficticia. Jenkins obtiene el código, ejecuta pruebas, construye una imagen identificable, la despliega en el color inactivo, valida la candidata, cambia el tráfico mediante Nginx y observa la función de turnos. Si la candidata falla después del cambio, el pipeline devuelve el tráfico al último color estable.

Se probaron tres escenarios: despliegue exitoso, rechazo antes de desplegar y fallo funcional posterior al cambio con rollback. En el laboratorio local se realizaron tres repeticiones de cada uno. Los tres despliegues exitosos registraron **0 errores en 2524 peticiones HTTP** externas. En las tres pruebas de rollback, la recuperación observada media fue **3,583 s** y la peor **3,75 s**, por debajo de la meta de 30 s. La topología se reprodujo en una VM EC2: el build exitoso #7 registró **0 errores en 86 peticiones** con un timeout de 5 s por petición, y el fallo inducido del build #3 se recuperó en **3,907 s** desde la primera muestra fallida. Estas cifras describen ventanas de ensayo, no un SLA de producción.

El alcance excluye historias clínicas reales, datos sensibles, bases de datos y alta disponibilidad de infraestructura. La cuenta Free solo financió la demostración. Para la clínica se preparó un **plan de costos incremental** que separa honorarios de consultoría y recursos AWS adicionales; el importe definitivo de AWS exige conocer la infraestructura y carga existentes. El estado visible del tablero Trello se registró mediante captura: cuatro tarjetas Por hacer, ninguna En curso, una A validar y trece Completado. El PR del pipeline está abierto para integración al repositorio principal.

## 1. Problema, objetivo y alcance

La segunda entrega plantea que el portal se actualizaba y revertía manualmente, con riesgo de indisponibilidad y poca trazabilidad. El caso de referencia describe una falla funcional de la Historia Clínica Electrónica que superaba controles técnicos básicos. La PoC responde a ese problema con validación funcional de una ruta de turnos ficticios, monitoreo externo y reversión automática. La meta explícita es 0 % de errores HTTP durante un despliegue exitoso y recuperación en menos de 30 s en el escenario de rollback.

El experimento usa una aplicación PHP y Apache sin persistencia, con tres turnos ficticios de solo lectura. La ruta `/api/turnos` representa una función visible para usuarios; `/version` muestra versión, commit, hora de construcción y color; `/health/live` y `/health/ready` distinguen salud técnica. No se procesan datos clínicos ni personales reales. La demostración se hizo primero en Docker local y después en una VM de AWS, como indica el roadmap de la segunda entrega.

## 2. Arquitectura implementada

| Componente | Función |
| --- | --- |
| Aplicación PHP y Apache | Sirve la pantalla de turnos, el JSON ficticio y los endpoints de versión y salud. |
| `his-blue` y `his-green` | Dos slots simétricos. Jenkins despliega la candidata solo en el inactivo. |
| Nginx | Es la entrada pública única; cambia el upstream activo y permite volver al color anterior. |
| Docker Compose | Define aplicación, proxy, red y configuración de ejecución. |
| Jenkins | Ejecuta el `Jenkinsfile` parametrizado con `happy`, `pre-test` o `post-deploy`. |
| Monitor externo | Consulta `/api/turnos` y `/version`, guarda CSV UTC y calcula tasas y tiempos observados. |
| EC2 Ubuntu 24.04 | Reproduce la topología con Docker rootless; aplicación y Jenkins se acceden por túnel SSH. |

La imagen se construye con `APP_VERSION`, `APP_COMMIT_SHA` y `APP_BUILD_TIME`; el color se aporta al ejecutar el contenedor. El estado de Blue y Green se conserva en `deploy/runtime/state.env`, que no se versiona. Los scripts rechazan un despliegue directo sobre el color activo. El cambio de tráfico valida el destino antes de recargar Nginx. En la VM, Jenkins usa el socket del daemon Docker rootless del usuario SSH y sus datos persisten en `deploy/runtime/jenkins-home`.

Durante las corridas documentadas de la VM, el acceso se realizó mediante túnel SSH hacia los puertos de loopback. El monitor corrió en Windows, fuera de Jenkins y fuera de la VM. Esta disposición permitió medir la respuesta que veía ese cliente externo al laboratorio, aunque añadió latencia de túnel a las mediciones.

## 3. Flujo de entrega y escenarios

El `Jenkinsfile` ejecuta: obtención del commit; instalación y pruebas PHP; conexión a la red del laboratorio; construcción de la candidata; despliegue en el color inactivo; smoke test funcional; **autorización manual**; cambio de tráfico; observación de la URL pública. El nuevo gate muestra versión, commit y color candidato, espera hasta 10 minutos la respuesta del ID `clinica-santa-lucia` y registra usuario y fecha UTC al aprobar. Si se rechaza o vence el plazo, no ejecuta el switch. Antes de promover vuelve a comprobar el color activo y el smoke test. Registra `PIPELINE_DURATION_SECONDS` al finalizar. El valor de `/version` permite vincular cada observación con el build y el commit.

La autorización se agregó **después** de las corridas cuantificadas en las secciones 5 y 6; sus cifras no prueban el gate. En los nuevos builds de EC2, [#8 aprobó](evidence/approval-gate-vm-2026-10-26/README.md) `ci-8-bfed65942a82` con el ID efectivo `Clinica-Santa-Lucia` a las `22:16:22Z` y solo entonces cambió Green → Blue. El build #9 rechazó `ci-9`; el #10 dejó vencer los 10 minutos sin respuesta. Ambos omitieron el switch y terminaron `ABORTED`. Después de cada uno, `/version` externo seguía sirviendo Blue `ci-8`. Ver la [guía de autorización](approval-gate.md).

| Escenario | Estímulo | Resultado esperado y observado |
| --- | --- | --- |
| A, `happy` | Candidata válida. | Jenkins termina `SUCCESS`; cambia el color y conserva la función de turnos. |
| B, `pre-test` | PHPUnit provoca un rechazo controlado. | El pipeline termina `FAILURE` antes de construir o desplegar; el público sigue en el color previo. |
| C, `post-deploy` | Tras el switch, la candidata responde 503 en Turnos mientras las rutas técnicas siguen disponibles. | Jenkins registra tres fallos consecutivos, ejecuta rollback al último color estable y termina `FAILURE` esperado. |

El fallo controlado de C se activa solo en la candidata mediante una marca temporal dentro del contenedor. El build fallido del escenario B o C no significa que haya fallado el mecanismo de la PoC: representa una release rechazada. La versión pública anterior se comprueba después del rollback.

## 4. Método de medición

El monitor toma muestras con fecha UTC. Cada muestra solicita `/api/turnos` y `/version`. Se considera correcta si Turnos responde 200 con identificadores ficticios válidos y `/version` responde 200 con una versión. La tasa de error HTTP cuenta cada petición; la tasa funcional cuenta muestras completas. El MTTD se mide entre el primer fallo de la caída inducida observado externamente y el tercer fallo consecutivo registrado por Jenkins. La recuperación observada mide el intervalo entre esa primera muestra fallida y la primera muestra correcta tras el rollback.

En el laboratorio local se solicitó una muestra cada 0,25 s y la latencia permitió aproximarse a ese intervalo. En EC2, las peticiones secuenciales a través del túnel produjeron una separación real de aproximadamente 1,3 s, por lo que la resolución temporal fue menor. Las primeras corridas de VM usaron timeout HTTP de 2 s y registraron timeouts aislados antes de los cambios de tráfico. La corrida exitosa #7 usó 5 s y dejó ese cambio de método explícito. Los intervalos y tasas no deben interpretarse como métricas de operación permanente ni compararse sin indicar el timeout.

## 5. Resultados del laboratorio local

Se ejecutaron nueve corridas: tres A, tres B y tres C. Jenkins leyó el commit `c819a5d810134364cf89d5f671bec530b0c1bcfc` del fork de trabajo.

| Escenario | Corridas | Resultado Jenkins | Errores HTTP externos | Duración media |
| --- | ---: | --- | ---: | ---: |
| A, despliegue exitoso | 3 | Tres `SUCCESS` | **0/2524 (0 %)** | 86 s |
| B, rechazo en pruebas | 3 | Tres `FAILURE` esperados | **0/494 (0 %)** | 5,667 s |
| C, fallo y rollback | 3 | Tres `FAILURE` esperados | 43/2054 (2,093 %) durante el fallo inducido | 72,333 s |

En C, el MTTD medio fue **2,039 s**. Las recuperaciones observadas fueron **3,75 s**, **3,50 s** y **3,50 s**; la media fue **3,583 s**. En las tres corridas, `/api/turnos` falló temporalmente con HTTP 503, `/version` siguió respondiendo y el tráfico volvió a la release anterior. La meta de recuperación inferior a 30 s se cumplió en este laboratorio. Los CSV y los nueve Console Output están en `docs/evidence/blue-green-local-2026-10-25/`.

## 6. Resultados de la VM EC2

La topología se reprodujo en una instancia Ubuntu 24.04 x86_64 de la región `us-east-2`, con Docker rootless y Jenkins en contenedor. El commit de aplicación usado durante las corridas de VM fue `7f1accc587b7`. El color público final observado fue Green `ci-7-7f1accc587b7`. Los CSV están en `docs/evidence/blue-green-vm-2026-10-25/`.

| Build y escenario | Resultado confirmado | Medición externa |
| --- | --- | --- |
| #2, `pre-test` | `FAILURE` esperado en pruebas, 2 s; etapas posteriores omitidas. | 15/15 muestras correctas; 0/30 peticiones fallidas; Green permaneció visible. |
| #3, `post-deploy` | `FAILURE` esperado, rollback Blue → Green, 29 s. | La caída inducida se detectó en 3,285 s y se recuperó en 3,907 s; el CSV completo tiene 4/64 peticiones fallidas, incluyendo un timeout previo separado. |
| #5, `happy` | `SUCCESS`, 39 s, Blue → Green. | 1/74 peticiones fallidas por un timeout previo al switch. |
| #6, `happy` | `SUCCESS`, 39 s; la versión pública pasó de Green `ci-5` a Blue `ci-6`. | 1/84 peticiones fallidas por un timeout previo al switch. |
| #7, `happy` | `SUCCESS`, 39 s, Blue → Green. | **0/86 peticiones fallidas y 0/43 muestras fallidas**, con timeout de 5 s. |

En el build #3, el tercer fallo de Jenkins quedó registrado a las `01:54:46.630Z`. Blue `ci-3` respondió 503 en Turnos y Green `ci-1` reapareció en la primera muestra correcta a las `01:54:47.241Z`. El CSV contiene además un timeout aislado de `/version` anterior a servir la candidata. En #5 y #6 también hubo un timeout aislado previo al cambio. Su causa no está identificada y se conservaron en la evidencia; el **0 %** corresponde específicamente al build #7. El Console Output completo de #6 y un extracto del de #2 acompañan los CSV de la VM.

## 7. Evaluación frente a los objetivos

| Objetivo de la segunda entrega | Evaluación |
| --- | --- |
| Pipeline de build, pruebas, despliegue inactivo, validación, switch y observación | Implementado en `Jenkinsfile` y ejecutado localmente y en EC2. |
| Autorización antes de cambiar tráfico | Incorporada después de las corridas históricas; #8 confirmó aprobación y switch posterior; #9 confirmó rechazo y #10 vencimiento, ambos sin switch y con la versión pública anterior. |
| Identificar Active, Release Candidate y LKG | Versión y commit visibles; color activo y objetivo en logs; rollback recibe el color estable previo. |
| Demostrar los escenarios A, B y C | Tres repeticiones por escenario en local; en VM hay CSV de los tres escenarios, con resultado y duración confirmados para #2, #3, #5, #6 y #7. |
| Medir HTTP, MTTD, recuperación, duración y versión | CSV externos, resúmenes, logs Jenkins y `/version` conservados. |
| Error 0 % en despliegue exitoso | Cumplido en las tres A locales y en la A de VM #7 bajo el timeout documentado. |
| Recuperación en menos de 30 s | Cumplido en las tres C locales y en la C de VM observada; no extrapolable a producción. |
| Gestión ágil con EDT, Gantt y Trello | La segunda entrega define EDT y responsables. La captura del tablero muestra 4 Por hacer, 0 En curso, 1 A validar y 13 Completado. Falta confirmar el cronograma definitivo y actualizar las tarjetas según la revisión grupal. |

## 8. Riesgos, límites y costos

**Alcance técnico.** La aplicación es stateless y usa datos ficticios. La PoC no prueba migraciones de bases de datos, sesiones, concurrencia clínica real, múltiples zonas, tolerancia a la caída de la VM ni seguridad de un HIS de producción. Nginx y Jenkins comparten una sola VM en la demo; si la VM falla, el esquema Blue Green no evita la interrupción. El rollback restaura el destino anterior, pero no revierte datos ni contratos externos.

**Medición.** El monitor solicita dos rutas en secuencia y depende del túnel SSH. Los timeouts previos al switch en las primeras corridas de VM no tienen causa demostrada. El timeout de 5 s de la corrida #7 reduce falsos fallos por latencia, pero también puede demorar la detección de un fallo real. El tiempo de recuperación informado es una observación discreta, no una garantía de RTO en otros entornos.

**Operación y seguridad.** La clave SSH y las credenciales de Jenkins deben permanecer fuera del repositorio. En la VM se usó Docker rootless; el acceso al daemon sigue siendo una capacidad operativa sensible y debe limitarse al laboratorio. El grupo debe conservar los CSV y Console Output antes de eliminar recursos de AWS. La guía de VM recomienda restringir SSH a la IP propia y acceder a la app y Jenkins por túnel.

**Costo de la propuesta.** La Clínica Santa Lucía ya utiliza AWS, por lo que se presupuesta solo el **incremento de Blue Green** y los honorarios de la consultora. El [plan detallado](plan-costos-consultoria.md) supone 100 h iniciales y 8 h/mes. Con una tarifa **hipotética de USD 40/h** y 15 % de contingencia inicial, resulta **USD 4600 una vez** y **USD 320/mes**; no es una oferta contractual. AWS se suma según segundo slot, almacenamiento y servicios que no puedan reutilizarse. El ejemplo del primer año es `USD 8440 + 13,8 × C_AWS`, con `C_AWS` igual al costo mensual incremental estimado en AWS Pricing Calculator. El importe definitivo exige el inventario de TI; los créditos Free de la demo no se aplican a la clínica.

## 9. Gestión del trabajo y trazabilidad

Según la segunda entrega, Valentina gestiona y documenta, Germán diseña la arquitectura e infraestructura, Ignacio desarrolla CI/CD y Joaquín cubre QA y observabilidad. La captura en `docs/evidence/kanban-2026-10-25.png` muestra **4 Por hacer** (3.1, 6.1, 6.2 y 6.3), **0 En curso**, **1 A validar** (1.2) y **13 Completado**. La imagen acredita el estado visible, pero no el detalle de cada tarjeta ni el Gantt. El grupo debe confirmar el cronograma definitivo y actualizar las tarjetas tras revisar esta entrega.

El código y la evidencia de esta PoC están propuestos en el PR #4 de `icamporroDEV/HospitalStLucia`, rama `codex/jenkins-blue-green` del fork `valenchillemi/HospitalStLucia`. Al preparar este informe, el PR estaba abierto y sin descripción ni revisiones registradas; su integración a `main` depende del propietario del repositorio.

## 10. Conclusiones y pasos de cierre

La PoC mostró que un cambio de versión puede validarse en el color inactivo y promoverse con 0 errores HTTP observados en las corridas exitosas seleccionadas. También mostró que una candidata técnicamente viva puede fallar en una función visible para usuarios: el escenario C produjo HTTP 503 en Turnos, Jenkins detectó la secuencia y restauró el color estable dentro de la meta medida. La versión de producción quedó identificable por commit y número de build.

Para cerrar la entrega grupal faltan: validar con la clínica las hipótesis de esfuerzo y capacidad para convertir el plan de costos en una cotización firme; confirmar el Gantt y actualizar las tarjetas de Trello según la revisión grupal; revisar el texto con los cuatro integrantes; y completar la descripción, revisión e integración del PR. Estos pendientes son de alcance y coordinación; no invalidan los resultados técnicos ya medidos.

## Anexo A Fuentes internas reproducibles

- Consigna y planificación: `TPO_Ingeniería de Software_Segunda Entrega.pdf`, páginas 4–12.
- Pipeline: `Jenkinsfile`; operaciones de despliegue: `deploy/bin/`; topología: `docker-compose.yml` y `docker-compose.vm.yml`.
- Protocolo de medición: `docs/monitoring.md` y `scripts/availability_monitor.py`.
- Laboratorio local: `docs/evidence/blue-green-local-2026-10-25/README.md`, CSV y nueve logs Jenkins.
- VM: `docs/vm-demo.md` y `docs/evidence/blue-green-vm-2026-10-25/README.md`, con cinco CSV y logs de #2 y #6.
- Autorización posterior: `docs/approval-gate.md` y `docs/evidence/approval-gate-vm-2026-10-26/README.md`, con extractos de Jenkins #8, #9 y #10 y lecturas externas tras rechazo y vencimiento.
- Presupuesto: `docs/plan-costos-consultoria.md`, con honorarios hipotéticos y modelo AWS incremental.
- Seguimiento: `docs/agents/issue-tracker.md`, captura `docs/evidence/kanban-2026-10-25.png` y tablero Trello del Grupo 03.
