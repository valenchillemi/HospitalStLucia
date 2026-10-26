# Evidencia del gate de autorización en la VM

El build Jenkins `hospital-vm` **#8** leyó el commit `bfed65942a822258520a78bc87f75072e2fab8da` y ejecutó el escenario `happy`. Esta es una corrida nueva, posterior a los resultados Blue Green documentados con el commit `7f1accc587b7`.

## Aprobación del build #8

PHPUnit terminó con 12 pruebas y 103 aserciones correctas. Jenkins desplegó la candidata `ci-8-bfed65942a82` en Blue, mientras Green siguió activo. Tras el smoke test, registró la solicitud a las `2026-10-26T22:16:11Z` y esperó una decisión humana. Jenkins informó `Approved by Clínica Santa Lucía` y `CAMBIO_APROBADO` para el ID efectivo `Clinica-Santa-Lucia` a las `22:16:22Z`. El switch `green -> blue` se ejecutó después de esa aprobación. Las 20 observaciones internas del pipeline fueron correctas y el job terminó en `SUCCESS` con `PIPELINE_DURATION_SECONDS=52`.

El [extracto del Console Output](jenkins-vm-8-approval-extract.log) conserva el orden y los campos de auditoría. La evidencia compartida no incluye un CSV de monitor externo ni una captura de `/version` antes de aprobar: por eso **no se informa una tasa de error HTTP externa** para esta corrida. El usuario de Jenkins aparece con mayúsculas en el registro efectivo, aunque la lista permitida del `Jenkinsfile` utiliza el ID de perfil en minúsculas `clinica-santa-lucia`.

## Rechazo del build #9

Una segunda candidata `ci-9-bfed65942a82` quedó validada en Green mientras Blue seguía activo. Jenkins registró `CAMBIO_SOLICITADO` a las `22:17:58Z`. El usuario rechazó la solicitud y Jenkins dejó `CAMBIO_NO_APROBADO` a las `22:18:33Z`. La etapa **Cambiar tráfico y observar** se omitió, el Console Output indicó `Rejected by Clínica Santa Lucía` y terminó `ABORTED` tras 53 s. El [extracto del build #9](jenkins-vm-9-rejection-extract.log) conserva estas líneas. Una consulta externa por el túnel después del rechazo devolvió `version=ci-8-bfed65942a82`, `commitSha=bfed65942a82` y `deploymentColor=blue`: la candidata Green `ci-9` no recibió el tráfico público. La lectura fue compartida como salida de PowerShell; no se adjuntó un archivo de monitor continuo para esa ventana.

## Control aún por ensayar

- **Vencimiento:** dejar transcurrir los 10 minutos del `input` y comprobar el mismo resultado.

La corrida de vencimiento debe conservar número de build, commit, líneas `CAMBIO_SOLICITADO` y `CAMBIO_NO_APROBADO`, estado final de Jenkins y `/version` público antes y después. Estas verificaciones no se sustituyen por el build #8 aprobado.
