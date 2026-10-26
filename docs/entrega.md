# Material de entrega de la PoC

| Material | Ubicación | Contenido |
| --- | --- | --- |
| Informe final para editar | [Word](informe-final.docx) y [Markdown](informe-final.md) | Problema, arquitectura, método, resultados, límites, riesgos y conclusiones. |
| Nueve corridas locales | [Evidencia local](evidence/blue-green-local-2026-10-25/README.md) | Tres A, tres B y tres C; CSV y Console Output de Jenkins. |
| Ensayo en EC2 | [Evidencia VM](evidence/blue-green-vm-2026-10-25/README.md) | Cinco CSV, log completo de #6, extracto de #2, despliegue exitoso y rollback. |
| Seguimiento ágil | [Captura de Trello](evidence/kanban-2026-10-25.png) | Estado visible: 4 Por hacer, 0 En curso, 1 A validar, 13 Completado. |
| Presupuesto de consultoría y AWS incremental | [Plan de costos](plan-costos-consultoria.md) | Horas, tarifa hipotética, componentes adicionales y fórmula del primer año. |
| Cuenta de demostración | [Consulta opcional](costos-aws.md) | Gasto y créditos de la cuenta Free; no determina el precio para la clínica. |
| Reproducción técnica | [Guía VM](vm-demo.md), [guía de despliegue](../deploy/README.md) y [monitoreo](monitoring.md) | Configuración, escenarios y comandos. |
| Autorización de promoción | [Guía del gate](approval-gate.md) | Aprobador permitido, plazo, auditoría y ensayos de aprobación, rechazo y vencimiento. |
| Evidencia nueva de autorización | [Jenkins VM #8 y #9](evidence/approval-gate-vm-2026-10-26/README.md) | Aprobación antes del switch y rechazo que omite el cambio; timeout por ensayar. |
| Presentación oral | [Guion de demo](guion-demo.md) | Secuencia sugerida, cifras que mostrar y aclaraciones metodológicas. |
| Trabajo propuesto para integrar | [PR #4](https://github.com/icamporroDEV/HospitalStLucia/pull/4) | Pipeline, laboratorio y documentación; requiere revisión del propietario. |
| Descripción del PR | [Texto listo para pegar](pr-description.md) | Resumen, evidencia y alcance del cambio. |

## Datos que debe confirmar el grupo antes de entregar

- Datos de carga, región y arquitectura AWS de la clínica para convertir el modelo incremental en una cotización definitiva.
- Completar el ensayo de timeout del gate; si se exige paridad, repetir los casos en Jenkins local.
- Estado final del Gantt y actualización de las tarjetas Trello después de la revisión grupal.
- Revisión de nombres, fecha de entrega, formato exigido por la cátedra y conclusiones firmadas por los cuatro integrantes.

No completar esos campos con estimaciones presentadas como hechos. Los resultados ya medidos y sus límites están desarrollados en el informe.
