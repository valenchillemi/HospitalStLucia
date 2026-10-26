## Summary

```text
Jenkinsfile                 Pruebas → candidata → aprobación → switch → observación/rollback
deploy/ y docker-compose*  Slots Blue/Green, Nginx y laboratorio EC2 rootless
scripts/                   Monitor externo y guía interactiva de EC2
docs/                      Informe final, guion y evidencias reproducibles
```

La PoC usa datos ficticios y no modifica un HIS de producción.

## Evidence

- **Antes:** el laboratorio no tenía un pipeline que desplegara en el color inactivo y revirtiera automáticamente una falla funcional posterior al switch.
- **Después, local:** nueve corridas (3 por escenario), con CSV y Console Output en [evidencia local](https://github.com/valenchillemi/HospitalStLucia/blob/1185a56/docs/evidence/blue-green-local-2026-10-25/README.md). Las A tuvieron 0/2524 errores HTTP; las C recuperaron el servicio en 3,50–3,75 s observados.
- **Después, EC2:** Jenkins #7 `SUCCESS` en 39 s, Blue → Green, 0/86 errores HTTP con timeout externo de 5 s. Jenkins #3 ejecutó rollback Blue → Green; recuperación observada 3,907 s. CSV y límites metodológicos en [evidencia VM](https://github.com/valenchillemi/HospitalStLucia/blob/1185a56/docs/evidence/blue-green-vm-2026-10-25/README.md).
- [Informe final editable](https://github.com/valenchillemi/HospitalStLucia/blob/1185a56/docs/informe-final.docx) · [Markdown](https://github.com/valenchillemi/HospitalStLucia/blob/1185a56/docs/informe-final.md) · [índice de entrega](https://github.com/valenchillemi/HospitalStLucia/blob/1185a56/docs/entrega.md).
- El build VM #2 terminó en `FAILURE` esperado antes del despliegue (2 s) y el #6 en `SUCCESS` (39 s). El [log #6 y extracto #2](https://github.com/valenchillemi/HospitalStLucia/blob/codex/jenkins-blue-green/docs/evidence/blue-green-vm-2026-10-25/README.md) acompañan los CSV.
- **Corrección posterior del profesor:** el `Jenkinsfile` espera autorización del ID de laboratorio `clinica-santa-lucia` hasta 10 minutos antes del switch y registra build, versión, commit, aprobador y fecha UTC. [Jenkins VM #8](https://github.com/valenchillemi/HospitalStLucia/blob/codex/jenkins-blue-green/docs/evidence/approval-gate-vm-2026-10-26/README.md) confirmó aprobación antes del switch; #9 confirmó rechazo y #10 vencimiento, ambos con etapa de switch omitida y Blue `ci-8` aún visible. Las corridas históricas no prueban este control nuevo.
- La [planificación de costos](https://github.com/valenchillemi/HospitalStLucia/blob/codex/jenkins-blue-green/docs/plan-costos-consultoria.md) estima honorarios de la consultora y consumo incremental de AWS, separado de la cuenta Free de la demo.
- Pendiente de la entrega grupal: validar las hipótesis de capacidad con la clínica, confirmar Gantt y actualizar las tarjetas Trello. La [captura del tablero](https://github.com/valenchillemi/HospitalStLucia/blob/codex/jenkins-blue-green/docs/evidence/kanban-2026-10-25.png) muestra 4 Por hacer, 0 En curso, 1 A validar y 13 Completado.

## Merge Danger

**Door:** two-way. El cambio se puede revertir por commit; la VM de demostración queda fuera del repositorio.

**Blast Radius:** laboratorio. La imagen y Jenkins usan Docker; la rama no incorpora datos clínicos reales. El nuevo gate puede detener una promoción si el usuario permitido no aprueba a tiempo. Revisar la configuración de identidad Jenkins, el socket rootless y el flujo de rollback antes de integrar.
