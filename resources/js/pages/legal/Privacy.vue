<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { buttonVariants } from '@/components/ui/button';
import { appName } from '@/lib/brand';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

interface Processor {
    name: string;
    purpose: string;
    data: string;
    location: string;
    url: string;
}

const props = defineProps<{
    version: string;
    updatedAt: string;
    controller: { name: string; url: string };
    contactEmail: string | null;
    processors: Processor[];
    retention: { pendingUploadHours: number; sessionMinutes: number };
}>();

const page = usePage<SharedData>();
const name = appName();
const updated = computed(() =>
    new Intl.DateTimeFormat('es', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${props.updatedAt}T00:00:00Z`)),
);

const sections = [
    ['resumen', 'En pocas palabras'],
    ['responsable', 'Quién es responsable'],
    ['datos', 'Qué datos tratamos'],
    ['finalidades', 'Para qué los usamos'],
    ['ia', 'Inteligencia artificial'],
    ['terceros', 'Con quién los compartimos'],
    ['cookies', 'Cookies y almacenamiento local'],
    ['conservacion', 'Cuánto tiempo los conservamos'],
    ['seguridad', 'Cómo los protegemos'],
    ['derechos', 'Tus derechos'],
    ['menores', 'Menores de edad'],
    ['cambios', 'Cambios en esta política'],
    ['contacto', 'Contacto'],
] as const;
</script>

<template>
    <Head title="Política de privacidad" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="mx-auto flex h-16 w-full max-w-4xl items-center justify-between px-6">
            <Link :href="route('home')" class="flex items-center" :aria-label="name"><AppLogo /></Link>
            <Link v-if="page.props.auth.user" :href="route('dashboard')" :class="cn(buttonVariants({ variant: 'outline' }), 'rounded-full px-5')">
                Ir al panel
            </Link>
            <Link v-else :href="route('login')" :class="cn(buttonVariants({ variant: 'outline' }), 'rounded-full px-5')">Iniciar sesión</Link>
        </header>

        <main class="mx-auto w-full max-w-4xl px-6 pb-24 pt-8">
            <p class="text-sm font-medium text-primary">Privacidad</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Política de privacidad</h1>
            <p class="mt-3 text-sm text-muted-foreground">Última actualización: {{ updated }} · versión {{ version }}</p>

            <nav class="mt-8 rounded-2xl border bg-card p-5" aria-label="Contenido de esta página">
                <ol class="grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
                    <li v-for="(section, index) in sections" :key="section[0]">
                        <a :href="`#${section[0]}`" class="text-muted-foreground hover:text-primary hover:underline"
                            >{{ index + 1 }}. {{ section[1] }}</a
                        >
                    </li>
                </ol>
            </nav>

            <article class="mt-10 space-y-12 text-[0.95rem] leading-7 text-muted-foreground">
                <section id="resumen" class="scroll-mt-8 rounded-2xl border border-primary/20 bg-primary-subtle p-6 text-foreground">
                    <h2 class="flex items-center gap-2 text-xl font-semibold tracking-tight">
                        <ShieldCheck class="size-5 text-primary" aria-hidden="true" /> 1. En pocas palabras
                    </h2>
                    <ul class="mt-4 list-disc space-y-2 pl-5">
                        <li>Guardamos lo mínimo: tu cuenta y lo que decides subir o preguntar.</li>
                        <li>
                            La inteligencia artificial recibe el texto de tu pregunta y los <strong>nombres</strong> de tus columnas;
                            <strong>nunca tus filas</strong>, tu correo ni los datos de tu cuenta.
                        </li>
                        <li>No vendemos tus datos y no usamos publicidad ni analítica de terceros.</li>
                        <li>Puedes borrar tus datos y tu cuenta en cualquier momento desde Configuración.</li>
                        <li>
                            {{ name }} es una <strong>demostración</strong>: no subas datos sensibles (salud, financieros, contraseñas, documentos de
                            identidad) ni información de terceros que no tengas derecho a tratar.
                        </li>
                    </ul>
                </section>

                <section id="responsable" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">2. Quién es responsable</h2>
                    <p class="mt-3">
                        {{ name }} es operada por
                        <a :href="controller.url" target="_blank" rel="noopener noreferrer" class="font-medium text-primary hover:underline">{{
                            controller.name
                        }}</a>, que decide para qué y cómo se tratan los datos personales de tu cuenta. Para los datos que tú subes dentro de tus
                        archivos, {{ controller.name }} actúa por encargo tuyo: tú decides qué subes y por qué.
                    </p>
                </section>

                <section id="datos" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">3. Qué datos tratamos</h2>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="font-medium text-foreground">Tu cuenta</dt>
                            <dd>
                                Nombre y correo electrónico. La contraseña se guarda siempre con un hash seguro, nunca en texto legible. También
                                guardamos la fecha y la versión de esta política que aceptaste al registrarte.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">Tu organización</dt>
                            <dd>Al registrarte se crea una organización para ti. Todo lo que subes o creas pertenece a ella.</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">Tus archivos y tus filas</dt>
                            <dd>
                                Los archivos CSV o Excel que subes. El archivo original se elimina en cuanto termina la importación (o a las
                                {{ retention.pendingUploadHours }} horas si no llegas a confirmarla). Las filas importadas se guardan en la base de
                                datos hasta que elimines ese conjunto de datos o tu cuenta. Si tus archivos contienen datos personales de otras
                                personas, tú eres quien responde de tener derecho a tratarlos.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">Tus dashboards y tus preguntas</dt>
                            <dd>
                                Guardamos cada dashboard y el texto de la pregunta con la que lo creaste, así como los cambios que pides y su
                                historial de versiones. Los números no se guardan copiados: se calculan cada vez que abres el dashboard.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">Datos técnicos</dt>
                            <dd>
                                Los proveedores de infraestructura registran datos técnicos de cada petición, entre ellos la dirección IP. La
                                aplicación guarda sus propios registros con identificadores internos, duración y errores, sin el contenido de tus
                                preguntas ni de tus datos.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-foreground">Lo que no recogemos</dt>
                            <dd>Datos de pago, ubicación, contactos, cookies de publicidad, píxeles de seguimiento ni analítica de terceros.</dd>
                        </div>
                    </dl>
                </section>

                <section id="finalidades" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">4. Para qué los usamos</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>
                            <strong class="text-foreground">Darte el servicio</strong> que pides: crear tu cuenta, importar tus archivos, diseñar y
                            mostrar tus dashboards. Es la base principal del tratamiento.
                        </li>
                        <li>
                            <strong class="text-foreground">Mantener la seguridad</strong>: limitar el uso abusivo (límites de intentos y de
                            generaciones), detectar errores y proteger la aplicación. Es un interés legítimo nuestro y tuyo.
                        </li>
                        <li><strong class="text-foreground">Cumplir obligaciones legales</strong> cuando una autoridad competente lo exija.</li>
                    </ul>
                    <p class="mt-3">
                        No tomamos decisiones automatizadas que tengan efectos legales sobre ti. La inteligencia artificial solo propone dashboards, y
                        eres tú quien los revisa y decide si los conservas o los cambias.
                    </p>
                </section>

                <section id="ia" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">5. Inteligencia artificial</h2>
                    <p class="mt-3">
                        Para diseñar dashboards y contestar preguntas de ayuda usamos la API de Gemini, de Google. A Google le enviamos:
                    </p>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        <li>el texto de tu pregunta o de tu petición de cambio,</li>
                        <li>los nombres de las columnas del archivo que elegiste (y su tipo: texto, número o fecha),</li>
                        <li>instrucciones fijas y una descripción del producto.</li>
                    </ul>
                    <p class="mt-3">
                        <strong class="text-foreground">Nunca enviamos tus filas</strong>, tu correo, tu nombre, el identificador de tu organización
                        ni datos de tu cuenta. Los números de los dashboards los calcula nuestro servidor; la inteligencia artificial no ejecuta
                        código ni consultas.
                    </p>
                    <p class="mt-3">
                        Mientras la integración use un plan gratuito, Google puede utilizar el contenido enviado para mejorar sus productos. Por eso
                        te pedimos <strong class="text-foreground">no escribir datos personales ni confidenciales en tus preguntas</strong> y ser
                        cuidadoso con los nombres de tus columnas.
                    </p>
                </section>

                <section id="terceros" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">6. Con quién los compartimos</h2>
                    <p class="mt-3">
                        No vendemos tus datos ni los cedemos a terceros con fines comerciales. Solo intervienen estos proveedores, que tratan datos
                        por nuestra cuenta y únicamente para prestar el servicio:
                    </p>
                    <div class="mt-4 overflow-x-auto rounded-2xl border bg-card">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <caption class="sr-only">
                                Proveedores que tratan datos
                            </caption>
                            <thead class="border-b bg-muted/40 text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" class="px-4 py-3 font-medium">Proveedor</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Para qué</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Qué datos</th>
                                    <th scope="col" class="px-4 py-3 font-medium">Dónde</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="processor in processors" :key="processor.name" class="align-top">
                                    <th scope="row" class="px-4 py-3 font-medium text-foreground">
                                        <a
                                            :href="processor.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="hover:text-primary hover:underline"
                                            >{{ processor.name }}</a
                                        >
                                    </th>
                                    <td class="px-4 py-3">{{ processor.purpose }}</td>
                                    <td class="px-4 py-3">{{ processor.data }}</td>
                                    <td class="px-4 py-3">{{ processor.location }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3">
                        Estos proveedores operan principalmente en Estados Unidos, así que tus datos pueden tratarse fuera de tu país. Al usar el
                        servicio aceptas esa transferencia internacional.
                    </p>
                </section>

                <section id="cookies" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">7. Cookies y almacenamiento local</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>
                            <strong class="text-foreground">Cookie de sesión</strong>, necesaria para mantenerte conectado; caduca tras
                            {{ retention.sessionMinutes }} minutos de inactividad.
                        </li>
                        <li><strong class="text-foreground">Cookie de protección CSRF</strong>, necesaria para que los formularios sean seguros.</li>
                        <li>
                            <strong class="text-foreground">Preferencias de la interfaz</strong>: el tema claro u oscuro (en el almacenamiento local
                            del navegador) y si el menú lateral está abierto (en una cookie).
                        </li>
                    </ul>
                    <p class="mt-3">
                        No usamos cookies de publicidad ni de analítica. La tipografía se sirve desde la propia aplicación, de modo que tu navegador
                        no contacta a ningún tercero para dibujar las páginas.
                    </p>
                </section>

                <section id="conservacion" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">8. Cuánto tiempo los conservamos</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>
                            <strong class="text-foreground">Archivos subidos</strong>: se eliminan al terminar la importación o a las
                            {{ retention.pendingUploadHours }} horas si no la confirmas.
                        </li>
                        <li>
                            <strong class="text-foreground">Filas, dashboards y preguntas</strong>: mientras no los elimines. Puedes borrar un
                            conjunto de datos o un dashboard cuando quieras.
                        </li>
                        <li>
                            <strong class="text-foreground">Cuenta</strong>: hasta que la elimines. Al eliminarla se borran de inmediato tu
                            organización y todo lo que contiene (archivos pendientes, conjuntos de datos y sus filas, dashboards, versiones e
                            historial), salvo que la organización tenga otras personas, en cuyo caso continúa sin ti.
                        </li>
                        <li>
                            <strong class="text-foreground">Copias de seguridad y registros</strong>: los proveedores de infraestructura pueden
                            conservar copias de seguridad y registros técnicos durante el plazo que fijan sus propias políticas, tras el cual
                            desaparecen.
                        </li>
                    </ul>
                </section>

                <section id="seguridad" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">9. Cómo los protegemos</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li>
                            <strong class="text-foreground">Cada organización ve solo lo suyo</strong>. El aislamiento se aplica en cada consulta y
                            falla cerrado: si no se sabe de qué organización es una petición, no se devuelve nada. Lo que pertenece a otra
                            organización responde como si no existiera, y hay pruebas automáticas que lo comprueban.
                        </li>
                        <li>Contraseñas guardadas con hash seguro, límite de intentos de inicio de sesión y protección CSRF.</li>
                        <li>Conexión cifrada (HTTPS), con cabeceras de seguridad y una política de contenido que impide ejecutar scripts ajenos.</li>
                        <li>
                            La inteligencia artificial no escribe ni ejecuta SQL: solo describe un dashboard que se valida contra un catálogo cerrado
                            de tus columnas antes de calcularse.
                        </li>
                        <li>
                            Los archivos se validan por tipo y tamaño, se guardan con nombres generados por la aplicación en una carpeta por
                            organización y se tratan siempre como datos (nunca se ejecutan sus fórmulas).
                        </li>
                        <li>Los secretos no están en el código, y las dependencias se revisan de forma automática y continua en busca de fallos.</li>
                        <li>Los proveedores de infraestructura cifran los datos almacenados según sus propias políticas.</li>
                    </ul>
                    <p class="mt-3">
                        Ningún sistema es invulnerable y esta aplicación no ha pasado por una auditoría de seguridad externa. Si encuentras una
                        vulnerabilidad, por favor repórtala de forma privada (ver contacto).
                    </p>
                </section>

                <section id="derechos" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">10. Tus derechos</h2>
                    <p class="mt-3">Puedes, en cualquier momento:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5">
                        <li><strong class="text-foreground">Acceder</strong> a tus datos y pedir una copia.</li>
                        <li><strong class="text-foreground">Rectificarlos</strong>: tu nombre y tu correo se cambian en Configuración → Perfil.</li>
                        <li>
                            <strong class="text-foreground">Eliminarlos</strong>: borra un conjunto de datos o un dashboard desde la aplicación, o tu
                            cuenta completa en Configuración → Perfil → Eliminar cuenta.
                        </li>
                        <li>
                            <strong class="text-foreground">Llevártelos</strong>: puedes descargar los datos de cada gráfica en CSV, y pedirnos una
                            copia de lo demás.
                        </li>
                        <li><strong class="text-foreground">Oponerte o limitar</strong> el tratamiento, y retirar tu consentimiento.</li>
                    </ul>
                    <p class="mt-3">
                        Para ejercer cualquiera de ellos escríbenos (ver contacto). Si crees que tus derechos no se han respetado, también puedes
                        reclamar ante la autoridad de protección de datos de tu país.
                    </p>
                </section>

                <section id="menores" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">11. Menores de edad</h2>
                    <p class="mt-3">
                        {{ name }} no está dirigida a menores de 18 años y no recogemos datos de menores de forma consciente. Si crees que un menor
                        creó una cuenta, avísanos y la eliminaremos.
                    </p>
                </section>

                <section id="cambios" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">12. Cambios en esta política</h2>
                    <p class="mt-3">
                        Si cambia de manera relevante, actualizaremos la fecha y la versión de arriba y lo avisaremos en la aplicación. La versión que
                        aceptaste al registrarte queda guardada junto a tu cuenta.
                    </p>
                </section>

                <section id="contacto" class="scroll-mt-8">
                    <h2 class="text-xl font-semibold tracking-tight text-foreground">13. Contacto</h2>
                    <p class="mt-3">Para ejercer tus derechos, resolver dudas sobre esta política o reportar una vulnerabilidad:</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        <li v-if="contactEmail">
                            Correo: <a :href="`mailto:${contactEmail}`" class="font-medium text-primary hover:underline">{{ contactEmail }}</a>
                        </li>
                        <li>
                            Sitio de {{ controller.name }}:
                            <a :href="controller.url" target="_blank" rel="noopener noreferrer" class="font-medium text-primary hover:underline">{{
                                controller.url
                            }}</a>
                        </li>
                    </ul>
                </section>
            </article>
        </main>
    </div>
</template>
