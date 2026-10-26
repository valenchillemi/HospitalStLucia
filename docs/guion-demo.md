# Guion de demostración Blue Green

Este guion reúne las acciones mínimas para presentar la PoC en la VM. La preparación completa está en [vm-demo.md](vm-demo.md). La evidencia histórica y las cifras del informe están en [informe-final.md](informe-final.md).

## Antes de exponer

1. Confirmar que EC2 y el túnel SSH estén activos. La aplicación debe abrir en `http://localhost:28080/version` y Jenkins en `http://localhost:28081`.
2. Consultar `/version` y `/api/turnos`; anotar el color y la versión actuales. No anunciar un color objetivo fijo: cada build `happy` despliega en el slot inactivo.
3. En Jenkins, abrir el job `hospital-vm` y mostrar `Build with Parameters` con los tres escenarios.
4. Tener a mano [la evidencia local](evidence/blue-green-local-2026-10-25/README.md) y [la de VM](evidence/blue-green-vm-2026-10-25/README.md). El build VM #7 ya documentado terminó en `SUCCESS` con 0/86 peticiones fallidas bajo timeout de 5 s; el build #3 documenta rollback en 3,907 s.

## Relato sugerido

**Arquitectura.** Mostrar que Blue y Green son dos versiones de la aplicación, Nginx es el acceso público y Jenkins publica en el color inactivo. Explicar que el commit, la hora de construcción y el color aparecen en `/version`; `/api/turnos` comprueba la función visible al usuario.

**Escenario A.** Ejecutar `happy` si se dispone del tiempo de demo. Mostrar que Jenkins prueba, construye, valida la candidata y queda esperando la [autorización manual](approval-gate.md). El usuario permitido revisa la versión y aprueba; el Console Output registra ID y fecha UTC antes del switch. Comparar color y versión de `/version` antes y después. El [build VM #8](evidence/approval-gate-vm-2026-10-26/README.md) ya verificó el orden de aprobación y switch; el ensayo histórico #7 tuvo 86/86 peticiones HTTP exitosas, pero ocurrió antes de agregar el gate.

**Escenario B.** Mostrar el [extracto del build VM #2](evidence/blue-green-vm-2026-10-25/jenkins-vm-2-extract.log) o ejecutar `pre-test`. La falla de PHPUnit es deliberada: el pipeline termina `FAILURE` antes del build y del despliegue; el color público debe mantenerse. En VM duró 2 s y el monitor registró 0/30 errores HTTP. Las tres repeticiones locales tuvieron 0/494 errores HTTP durante sus ventanas de medición.

**Escenario C.** Mostrar el Console Output y CSV del build VM #3. Explicar que la candidata pasa el smoke test, recibe tráfico, pero Turnos empieza a responder 503. Jenkins registra tres fallos consecutivos y ejecuta `Rollback OK: blue -> green`. La primera muestra correcta después del fallo inducido llegó 3,907 s tras la primera muestra fallida; el límite definido era 30 s. El `FAILURE` final indica rechazo de esa release, no pérdida permanente del servicio.

**Gestión del cambio.** El [build VM #9](evidence/approval-gate-vm-2026-10-26/README.md) documenta un rechazo: `CAMBIO_NO_APROBADO`, etapa de switch omitida y `ABORTED`; `/version` externo conservó Blue `ci-8`. Explicar que se espera el mismo comportamiento si transcurren 10 minutos sin respuesta; ese ensayo sigue pendiente.

## Qué aclarar al mostrar métricas

- Las tasas y tiempos corresponden a ventanas de ensayo, no a un SLA de producción.
- En VM, el túnel SSH hizo que las muestras quedaran separadas aproximadamente 1,3 s aunque el intervalo solicitado fuera 0,25 s.
- Las corridas VM #3, #5 y #6 registraron timeouts aislados antes del cambio de tráfico. El 0 % de errores de VM corresponde al build #7, medido con timeout de 5 s por petición; las corridas anteriores usaron 2 s.
- La PoC usa datos ficticios, no incluye base de datos ni prueba la caída de la VM.

## Cierre

Mostrar el [informe final](informe-final.md), el PR #4 y los CSV. Antes de terminar la sesión, comprobar de nuevo `/version` y `/api/turnos`. Guardar cualquier captura faltante y revisar los recursos de AWS al finalizar la demostración.
