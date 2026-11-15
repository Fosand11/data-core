# Política de seguridad

Gracias por ayudar a mantener seguro este proyecto y los datos de quien lo usa.

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Usa el reporte privado de GitHub:
[Reportar una vulnerabilidad](https://github.com/Fosand11/data-core/security/advisories/new).

Incluye, si puedes: qué encontraste, cómo reproducirlo (pasos o una prueba de concepto mínima), qué impacto crees que tiene y
qué versión o URL probaste. No hace falta que propongas la solución.

*English: please report privately through the GitHub link above, not in a public issue.*

## Qué esperar

Es un proyecto personal y de portafolio, mantenido a mejor esfuerzo, no un servicio con soporte 24/7. Aun así:

- Acusamos recibo en un plazo de **5 días hábiles**.
- Te contamos si lo consideramos válido y, en general, lo corregimos en un plazo de **30 días** (antes si es grave).
- Te damos crédito si quieres, una vez corregido.

## Alcance

Entra en el alcance el código de este repositorio y el demo desplegado (`insightflow-3j5k.onrender.com`). Especialmente interesa:

- Que una organización pueda ver, cambiar o borrar datos de otra (aislamiento entre organizaciones).
- Inyección (SQL, scripts, fórmulas en archivos CSV/Excel), subida de archivos peligrosos o recorrido de rutas.
- Saltarse la autenticación, la autorización o los límites de uso.
- Que la inteligencia artificial reciba filas de datos o ejecute algo más que describir un dashboard.
- Filtración de secretos o de datos personales.

Fuera de alcance: ataques de denegación de servicio por volumen, ingeniería social, hallazgos que exijan acceso físico o un
dispositivo ya comprometido, y vulnerabilidades de los servicios de terceros (Render, Neon, Google), que debes reportar a
ellos. Los límites de uso del demo son intencionales.

## Reglas de buena fe

Si actúas de buena fe, sin acceder a datos de otras personas más de lo necesario para demostrar el problema, sin degradar el
servicio y reportándolo en privado, no emprenderemos acciones contra ti. Usa tus propias cuentas de prueba.

## Versiones con soporte

Solo la rama `main`, que es lo que está desplegado.

## Lo que ya hacemos de forma continua

- **Dependencias**: Dependabot propone actualizaciones cada semana y un flujo de auditoría (`composer audit` y `pnpm audit`)
  corre en cada cambio y cada lunes.
- **Análisis estático**: CodeQL revisa el código TypeScript y las pruebas automáticas cubren el aislamiento entre
  organizaciones, la validación de entradas y las cabeceras de seguridad.
- **Cabeceras**: HTTPS estricto (HSTS), política de contenido (CSP) con *nonce*, sin *framing*, sin *sniffing* y permisos de
  dispositivo denegados.
- **Secretos** fuera del repositorio y archivos del servidor sin permisos innecesarios.

La política de privacidad de la aplicación está en `/privacidad`.
