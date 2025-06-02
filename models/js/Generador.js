document.addEventListener('DOMContentLoaded', function () {
    cargarClasificaciones();

    const folioInput = document.getElementById('folio');

    folioInput.addEventListener('input', function () {
        if (!this.value.trim()) {
            limpiarCampos();
        } else {
            verificarFolio(this.value);
        }
    });

    const closeBtn = document.querySelector('.close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            document.getElementById('qr-modal').style.display = "none";
        });
    }
});

document.getElementById('toggle-name-input').addEventListener('change', function() {
    document.getElementById('name-input-container').style.display = this.checked ? 'block' : 'none';
});

document.getElementById('toggle-email-input').addEventListener('change', function () {
    const emailInputContainer = document.getElementById('email-input-container');
    emailInputContainer.style.display = this.checked ? 'block' : 'none';
});

document.getElementById('toggle-email-input').addEventListener('change', function () {
    const emailInputContainer = document.getElementById('email-input-container');
    emailInputContainer.style.display = this.checked ? 'block' : 'none';
});

document.getElementById('toggle-email-input').addEventListener('change', function () {
    document.getElementById('email-input-container').style.display = this.checked ? 'block' : 'none';
});

document.getElementById('toggle-phone-input').addEventListener('change', function () {
    document.getElementById('phone-input-container').style.display = this.checked ? 'block' : 'none';
});


document.getElementById("generar-btn").addEventListener("click", async function () {
    limpiarCampos();
    const btnGenerar = this;
    btnGenerar.disabled = true; // Deshabilitar botón para evitar múltiples clics

    const folio = document.getElementById("folio").value;
    const nombreReceptor = document.getElementById("nombre-receptor").value;
    const fecha = document.getElementById("fecha").value;
    const hora = document.getElementById("hora").value;

    const fechaDisabled = document.getElementById("fecha").disabled;
    const horaDisabled = document.getElementById("hora").disabled;
    const nombreDisabled = document.getElementById("nombre-receptor").disabled;

    try {
        // Validación de fecha y hora
        if (!fechaDisabled || !horaDisabled) {
            if (!fecha || !hora) {
                throw new Error('Debe seleccionar fecha y hora del evento');
            }

            const verifEvento = await fetch('../Controllers/GeneradorController.php?action=verificarEvento', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fecha: fecha,
                    hora: hora
                })
            }).then(res => res.json());

            if (!verifEvento.success || !verifEvento.exists) {
                throw new Error(verifEvento.error || 'No existe un evento programado para la fecha y hora seleccionadas');
            }
        }

        // Paso 1: Verificar si el folio existe
        let response = await fetch('../Controllers/GeneradorController.php?action=obtenerDatosCompra', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ folio: folio })
        });

        let data = await response.json();

        // Si el folio existe, mostrar QR inmediatamente
        if (data.success) {
            const qrData = JSON.stringify({
                "Compra": [
                    "COMPRA_ID", data.data.COMPRA_ID,
                    "FOLIO", data.data.FOLIO,
                    "FECHA", data.data.FECHA_EVENTO || fecha
                ]
            });
            mostrarQR(qrData, data.data.FOLIO);
            btnGenerar.disabled = false;
            return;
        }

        // Si no existe, validar datos para creación
        if (!nombreReceptor && !nombreDisabled) {
            throw new Error('Debe ingresar el nombre del receptor para un folio nuevo');
        }

        const clasificaciones = {};
        document.querySelectorAll('#clasificaciones-container input[type="checkbox"]:checked').forEach(checkbox => {
            const cantidadInput = document.getElementById(`cantidad-${checkbox.id}`);
            if (cantidadInput && cantidadInput.value > 0) {
                clasificaciones[checkbox.value] = parseInt(cantidadInput.value);
            }
        });

        if (Object.keys(clasificaciones).length === 0) {
            document.getElementById('cantidad-message').textContent = 'Debe ingresar al menos una cantidad de boletos';
            btnGenerar.disabled = false;
            return;
        }

        // Paso 2: Crear nuevo registro
        response = await fetch('../Controllers/GeneradorController.php?action=crearCompra', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                folio: folio,
                nombre_receptor: nombreReceptor,
                fecha: fecha,
                hora: hora,
                clasificaciones: clasificaciones
            })
        });

        data = await response.json();

        // Si hay error de duplicado, obtener datos nuevamente
        if (!data.success && data.error.includes('Duplicate entry')) {
            response = await fetch('../Controllers/GeneradorController.php?action=obtenerDatosCompra', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ folio: folio })
            });

            data = await response.json();
        }

        if (!data.success) {
            throw new Error(data.error || 'Error al procesar la compra');
        }

        // Paso 3: Obtener datos completos después de crear
        response = await fetch('../Controllers/GeneradorController.php?action=obtenerDatosCompra', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ folio: folio })
        });

        data = await response.json();

        if (!data.success) {
            throw new Error('No se pudieron obtener los datos completos de la compra');
        }

        // Mostrar QR con datos completos
        const qrData = JSON.stringify({
            "Compra": [
                "COMPRA_ID", data.data.COMPRA_ID,
                "FOLIO", data.data.FOLIO,
                "FECHA", data.data.FECHA_EVENTO || fecha
            ]
        });

        mostrarQR(qrData, data.data.FOLIO);

    } catch (error) {
        console.error("Error:", error);
        if (error.message.includes('fecha') || error.message.includes('hora') || error.message.includes('evento')) {
            document.getElementById('fecha-message').textContent = error.message;
            document.getElementById('fecha-message').style.color = 'red';
            document.getElementById('hora-message').textContent = error.message;
            document.getElementById('hora-message').style.color = 'red';
        } else {
            alert("Error al procesar: " + error.message);
        }
    } finally {
        btnGenerar.disabled = false;
    }
});

// Al cargar la página, verificamos si hay datos de folio generado para mostrar el QR
document.addEventListener('DOMContentLoaded', function() {
    const folioGenerado = sessionStorage.getItem('folioGenerado');
    if (folioGenerado) {
        const { folio, compraId, fecha } = JSON.parse(folioGenerado);
        
        const qrData = JSON.stringify({
            "Compra": [
                "COMPRA_ID", compraId,
                "FOLIO", folio,
                "FECHA", fecha
            ]
        });
        
        mostrarQR(qrData, folio);
        
        // Limpiamos el storage para no mostrar el QR en futuras recargas
        sessionStorage.removeItem('folioGenerado');
    }
});

document.getElementById("enviar-correo-btn").addEventListener("click", async function () {
    // Obtener ambos nombres
    const nombreOriginal = document.getElementById("nombre-receptor").value;
    const usarNombreManual = document.getElementById("toggle-name-input").checked;
    const nombreParaMensaje = usarNombreManual 
        ? document.getElementById("manual-name").value 
        : nombreOriginal;
    
    // Resto de datos
    const fecha = document.getElementById("fecha").value;
    const hora = document.getElementById("hora").value;
    const folio = document.getElementById("folio").value;
    const qrCanvas = document.querySelector("#qr-code-container canvas");
    const usarCorreoManual = document.getElementById("toggle-email-input").checked;
    const correoManual = document.getElementById("manual-email").value;

    if (!nombreParaMensaje || !qrCanvas) {
        mostrarModal({
            title: 'Datos incompletos',
            message: 'Asegúrate de haber generado el QR y llenado el nombre del receptor.',
            icon: 'error'
        });
        return;
    }

    try {
        let correoDestino = "";

        if (usarCorreoManual) {
            if (!correoManual || !correoManual.includes("@")) {
                throw new Error('Por favor ingresa un correo válido');
            }
            correoDestino = correoManual;
        } else {
            // Buscar correo usando el nombre ORIGINAL
            const clienteResponse = await fetch('../Controllers/GeneradorController.php?action=obtenerDatosClientePorNombre', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ nombre_receptor: nombreOriginal })
            });

            const clienteData = await parseResponse(clienteResponse);
            if (!clienteData.success || !clienteData.data.correo) {
                throw new Error("No se encontró correo para el receptor original. Ingresa un correo manualmente.");
            }
            correoDestino = clienteData.data.correo;
        }

        const qrBase64 = qrCanvas.toDataURL("image/png");
        const formData = new FormData();
        formData.append('name', nombreParaMensaje); // Usar nombre modificado para el mensaje
        formData.append('email', correoDestino);
        formData.append('subject', 'Boletos Vinicola Parvada');
        formData.append('qrBase64', qrBase64);
        formData.append('fecha', fecha);
        formData.append('hora', hora);
        formData.append('folio', folio);

        const mailResponse = await fetch('../Controllers/enviar_correo.php?action=enviarCorreo', {
            method: 'POST',
            body: formData
        });

        const mailResult = await parseResponse(mailResponse);

        if (!mailResult.success) {
            throw new Error(mailResult.error || "Error al enviar el correo");
        }

        if (mailResult.modal) {
            mostrarModal({
                title: mailResult.title,
                message: mailResult.message,
                icon: mailResult.icon || 'success'
            });
        }

    } catch (error) {
        console.error("Error al enviar correo:", error);
        mostrarModal({
            title: 'Error',
            message: `Error al enviar correo: ${error.message}`,
            icon: 'error'
        });
    }
});

document.getElementById("enviar-btn").addEventListener("click", async function () {
    // Obtener el nombre original del receptor
    const nombreOriginal = document.getElementById("nombre-receptor").value;
    
    // Verificar si se está usando un nombre manual
    const usarNombreManual = document.getElementById("toggle-name-input").checked;
    const nombreParaMensaje = usarNombreManual 
        ? document.getElementById("manual-name").value 
        : nombreOriginal;
    
    // Resto de los datos del formulario
    const fecha = document.getElementById("fecha").value;
    const hora = document.getElementById("hora").value;
    const folio = document.getElementById("folio").value;
    const qrCanvas = document.querySelector("#qr-code-container canvas");
    const usarTelefonoManual = document.getElementById("toggle-phone-input").checked;
    const telefonoManual = document.getElementById("manual-phone").value;

    // Validaciones básicas
    if (!nombreParaMensaje || !qrCanvas) {
        mostrarModal({
            title: 'Datos incompletos',
            message: 'Asegúrate de haber generado el QR y llenado el nombre del receptor.',
            icon: 'error'
        });
        return;
    }

    try {
        let telefonoDestino = "";

        // Obtener teléfono (manual o de la base de datos)
        if (usarTelefonoManual) {
            if (!telefonoManual) {
                mostrarModal({
                    title: 'Teléfono inválido',
                    message: 'Por favor ingresa un número de teléfono válido.',
                    icon: 'error'
                });
                return;
            }
            telefonoDestino = telefonoManual;
        } else {
            // Buscar teléfono usando el nombre ORIGINAL (no el modificado)
            const clienteResponse = await fetch('../Controllers/GeneradorController.php?action=obtenerDatosClientePorNombre', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ nombre_receptor: nombreOriginal })
            });

            const clienteData = await parseResponse(clienteResponse);
            if (!clienteData.success || !clienteData.data.telefono) {
                throw new Error("No se encontró teléfono para el receptor. Ingresa un teléfono manualmente.");
            }
            telefonoDestino = clienteData.data.telefono;
        }

        // Generar QR en base64
        const qrBase64 = qrCanvas.toDataURL("image/png");

        // Preparar datos para enviar
        const formData = new FormData();
        formData.append('name', nombreParaMensaje);  // Usar nombre modificado para el mensaje
        formData.append('telefono', telefonoDestino);
        formData.append('qrBase64', qrBase64);
        formData.append('fecha', fecha);
        formData.append('hora', hora);
        formData.append('folio', folio);

        // Enviar por WhatsApp
        const whatsappResponse = await fetch('../Controllers/enviar_whatsapp_Parv.php?action=enviarWhatsapp', {
            method: 'POST',
            body: formData
        });

        const whatsappResult = await parseResponse(whatsappResponse);

        if (!whatsappResult.success) {
            throw new Error(whatsappResult.error);
        }

        // Mostrar confirmación
        mostrarModal({
            title: whatsappResult.title || 'Éxito',
            message: whatsappResult.message || 'Mensaje enviado correctamente',
            icon: whatsappResult.icon || 'success'
        });

    } catch (error) {
        mostrarModal({
            title: 'Error',
            message: 'Error al enviar mensaje favor de interntarlo nuevamente' + error.message,
            icon: 'error'
        });
    }
});


document.getElementById("fecha").addEventListener("change", function () {
    document.getElementById("fecha-message").textContent = "";
    document.getElementById("hora-message").textContent = "";
});

document.getElementById("hora").addEventListener("change", function () {
    document.getElementById("fecha-message").textContent = "";
    document.getElementById("hora-message").textContent = "";
});

const qrCode = new QRCodeStyling({
    width: 300,
    height: 300,
    data: "",
    image: "../Assets/imagenQR.png",
    dotsOptions: {
        color: "#000000",
        type: "square"
    },
    backgroundOptions: {
        color: "#ffffff"
    },
    imageOptions: {
        crossOrigin: "anonymous",
        margin: 10,
        imageSize: 0.4
    }
});

function mostrarQR(data, folio) {
    qrCode.update({ data: data });
    document.getElementById("qr-code-container").innerHTML = "";
    qrCode.append(document.getElementById("qr-code-container"));
    document.getElementById("qr-modal").style.display = "block";

    // Restaurar el nombre manual si existe
    const nombreManual = sessionStorage.getItem('nombreManual');
    if (nombreManual) {
        document.getElementById("manual-name").value = nombreManual;
        document.getElementById("toggle-name-input").checked = true;
        document.getElementById("name-input-container").style.display = 'block';
    }

    guardarQR(folio);
}

async function verificarQRExistente(folio) {
    try {
        const response = await fetch('../Controllers/GeneradorController.php?action=verificarQR', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ folio: folio })
        });

        const data = await response.json();
        return data.exists;
    } catch (error) {
        console.error("Error al verificar QR:", error);
        return false;
    }
}

async function guardarQR(folio) {
    try {
        const canvas = document.querySelector('#qr-code-container canvas');
        if (!canvas) {
            throw new Error('No se encontró el elemento canvas del QR');
        }

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, 'image/png');
        });

        if (!blob) {
            throw new Error('Error al convertir el QR a imagen');
        }

        const formData = new FormData();
        formData.append('folio', folio);
        formData.append('qr', blob, `${folio}.png`);

        const response = await fetch('../Controllers/GeneradorController.php?action=guardarQR', {
            method: 'POST',
            body: formData,
        });

        const data = await response.json();

        if (!data.success) {
            console.error('Error del servidor:', data.error || 'Error desconocido');
        }
    } catch (error) {
        console.error('Error al guardar QR:', error);
    }
}

async function cargarClasificaciones() {
    try {
        const response = await fetch('../Controllers/GeneradorController.php?action=obtenerClasificaciones');

        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`La respuesta no es JSON: ${text.substring(0, 100)}...`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error en los datos del servidor');
        }

        renderizarClasificaciones(data.data);

    } catch (error) {
        console.error('Error al cargar clasificaciones:', error);
        alert('Error al cargar clasificaciones. Ver consola para detalles.');
    }
}

async function verificarFechaHora(fecha, hora) {
    if (!fecha || !hora) {
        return { success: false, error: 'Fecha y hora son obligatorias' };
    }

    try {
        const response = await fetch('../Controllers/GeneradorController.php?action=verificarEvento', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                fecha: fecha,
                hora: hora
            })
        });

        return await response.json();
    } catch (error) {
        console.error('Favor de ingresar una hora valida', error);
        return { success: false, error: 'Error al conectar con el servidor' };
    }
}

function renderizarClasificaciones(clasificaciones) {
    const container = document.getElementById('clasificaciones-container');
    container.innerHTML = '';

    clasificaciones.forEach(clasificacion => {
        const idStr = String(clasificacion.CLASIFICACION_ID);
        const safeId = idStr.toLowerCase().replace(/\s+/g, '_');

        const groupDiv = document.createElement('div');
        groupDiv.className = 'checkbox-group';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = safeId;
        checkbox.name = 'clasificacion[]';
        checkbox.value = idStr;
        checkbox.onchange = () => toggleCantidad(safeId);

        const label = document.createElement('label');
        label.htmlFor = safeId;
        label.textContent = clasificacion.DESCRIPCION;

        const inputCantidad = document.createElement('input');
        inputCantidad.type = 'number';
        inputCantidad.id = `cantidad-${safeId}`;
        inputCantidad.name = `cantidad[${idStr}]`;
        inputCantidad.min = '1';
        inputCantidad.style.display = 'none';
        inputCantidad.placeholder = 'Cantidad';
        inputCantidad.required = false;

        groupDiv.appendChild(checkbox);
        groupDiv.appendChild(label);
        groupDiv.appendChild(inputCantidad);
        container.appendChild(groupDiv);
    });
}

function toggleCantidad(clasificacion) {
    const input = document.getElementById(`cantidad-${clasificacion}`);
    const checkbox = document.getElementById(clasificacion);

    if (checkbox.checked) {
        input.style.display = 'inline-block';
        input.required = true;
    } else {
        input.style.display = 'none';
        input.required = false;
        input.value = '';
    }
}

function verificarFolio(folio) {
    const buscarReceptorBtn = document.getElementById('buscar-receptor-btn');

    if (!folio || !folio.trim()) {
        limpiarCampos();
        buscarReceptorBtn.disabled = false;
        return;
    }

    limpiarCampos();
    fetch('../Controllers/GeneradorController.php?action=buscarFolio', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ folio: folio })
    })
        .then(response => response.json())
        .then(data => {
            const messageElement = document.getElementById('folio-message');
            const receptorInput = document.getElementById('nombre-receptor');
            const fechaInput = document.getElementById('fecha');
            const horaInput = document.getElementById('hora');

            if (data.success) {
                limpiarCampos();
                receptorInput.value = data.receptor;
                receptorInput.disabled = true;
                messageElement.textContent = 'Folio encontrado';
                messageElement.style.color = 'green';

                buscarReceptorBtn.disabled = true;
                buscarReceptorBtn.classList.add('disabled');

                if (data.fechaEvento) {
                    fechaInput.value = data.fechaEvento;
                    fechaInput.disabled = true;
                }
                if (data.horaEvento) {
                    horaInput.value = data.horaEvento;
                    horaInput.disabled = true;
                }

                cargarBoletosPorFolio(folio);
            } else {
                limpiarCampos();
                receptorInput.value = '';
                receptorInput.disabled = false;
                messageElement.textContent = 'Folio no encontrado. Se creara una nueva compra.';
                messageElement.style.color = 'red';

                buscarReceptorBtn.disabled = false;
                buscarReceptorBtn.classList.remove('disabled');

                fechaInput.value = '';
                horaInput.value = '';
                fechaInput.disabled = false;
                horaInput.disabled = false;

                habilitarEdicion();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('folio-message').textContent = 'Error al conectar con el servidor';
            document.getElementById('folio-message').style.color = 'red';

            buscarReceptorBtn.disabled = false;
            buscarReceptorBtn.classList.remove('disabled');

            document.getElementById('fecha').disabled = false;
            document.getElementById('hora').disabled = false;
            document.getElementById('nombre-receptor').disabled = false;
        });
}

async function cargarBoletosPorFolio(folio) {
    try {
        const response = await fetch('../Controllers/GeneradorController.php?action=obtenerBoletos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ folio: folio })
        });

        const data = await response.json();

        if (data.success && data.data.length > 0) {
            document.querySelectorAll('#clasificaciones-container input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.disabled = true;
            });

            document.querySelectorAll('#clasificaciones-container input[type="number"]').forEach(input => {
                input.value = '';
                input.style.display = 'none';
                input.disabled = true;
            });

            data.data.forEach(tipo => {
                const safeId = String(tipo.CLASIFICACION_ID).toLowerCase().replace(/\s+/g, '_');
                const checkbox = document.getElementById(safeId);
                const cantidadInput = document.getElementById(`cantidad-${safeId}`);

                if (checkbox && cantidadInput) {
                    checkbox.checked = true;
                    cantidadInput.value = tipo.CANTIDAD;
                    cantidadInput.style.display = 'inline-block';
                    cantidadInput.disabled = true;
                }
            });

            const primerRegistro = data.data[0];
            const fechaEvento = new Date(primerRegistro.FECHA);
            const fechaFormateada = fechaEvento.toISOString().split('T')[0];
            const horaEvento = primerRegistro.HORA_INICIO;
            const horaFormateada = horaEvento.substring(0, 5);

            const fechaInput = document.getElementById('fecha');
            const horaInput = document.getElementById('hora');

            fechaInput.value = fechaFormateada;
            fechaInput.disabled = true;

            horaInput.value = horaFormateada;
            horaInput.disabled = true;
        } else {
            document.getElementById('fecha').disabled = false;
            document.getElementById('hora').disabled = false;
            document.getElementById('nombre-receptor').disabled = false;
        }
    } catch (error) {
        console.error('Error al cargar boletos:', error);
        document.getElementById('fecha').disabled = false;
        document.getElementById('hora').disabled = false;
        document.getElementById('nombre-receptor').disabled = false;
    }
}

function habilitarEdicion() {
    document.querySelectorAll('#clasificaciones-container input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.disabled = false;
    });

    document.querySelectorAll('#clasificaciones-container input[type="number"]').forEach(input => {
        input.value = '';
        input.style.display = 'none';
        input.disabled = false;
    });
}

async function parseResponse(response) {
    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch (e) {
        console.error("Respuesta no JSON:", text);
        throw new Error(`El servidor respondió con un error: ${text.substring(0, 100)}...`);
    }
}

function limpiarCampos() {
    document.getElementById('fecha-message').textContent = '';
    document.getElementById('hora-message').textContent = '';
    document.getElementById('cantidad-message').textContent = '';
}

function mostrarModal({ title, message, icon = 'info' }) {
    document.querySelectorAll('#notification-modal').forEach(el => el.remove());

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = 'notification-modal';

    const iconMap = {
        success: { color: '#4CAF50', symbol: '✓' },
        error: { color: '#f44336', symbol: '✗' },
        warning: { color: '#FFA500', symbol: '⚠' },
        info: { color: '#2196F3', symbol: 'ⓘ' }
    };

    const iconConfig = iconMap[icon] || iconMap.info;

    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-icon" style="font-size: 50px; color: ${iconConfig.color}; margin: 20px 0;">
                ${iconConfig.symbol}
            </div>
            <h3>${title}</h3>
            <p>${message}</p>
            <button id="close-modal-btn">Aceptar</button>
        </div>
    `;

    document.body.appendChild(modal);

    const closeModal = () => modal.remove();
    modal.querySelector('.close').onclick = closeModal;
    modal.querySelector('#close-modal-btn').onclick = closeModal;
    modal.onclick = (e) => e.target === modal && closeModal();

    modal.style.display = 'block';
}

let currentReceptorPage = 1;
const receptorsPerPage = 10;
let totalReceptors = 0;
let allReceptors = [];

document.getElementById('buscar-receptor-btn').addEventListener('click', function () {
    document.getElementById('receptor-modal').style.display = 'block';
    currentReceptorPage = 1;
    buscarReceptores();
});

document.getElementById('buscar-receptor-input').addEventListener('click', function () {
    currentReceptorPage = 1;
    buscarReceptores();
});

document.getElementById('buscar-receptor-input').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        currentReceptorPage = 1;
        buscarReceptores();
    }
});

let debounceTimeout;

document.getElementById('buscar-receptor-input').addEventListener('input', function () {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        currentReceptorPage = 1;
        buscarReceptores();
    }, 300);
});

document.getElementById('prev-page').addEventListener('click', function () {
    if (currentReceptorPage > 1) {
        currentReceptorPage--;
        buscarReceptores();
    }
});

document.getElementById('next-page').addEventListener('click', function () {
    const totalPages = Math.ceil(totalReceptors / receptorsPerPage);
    if (currentReceptorPage < totalPages) {
        currentReceptorPage++;
        buscarReceptores();
    }
});

async function buscarReceptores() {
    const searchTerm = document.getElementById('buscar-receptor-input').value;

    document.getElementById('receptores-tbody').innerHTML = '<tr><td colspan="3">Cargando...</td></tr>';

    try {
        const response = await fetch('../Controllers/GeneradorController.php?action=buscarReceptores', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                searchTerm: searchTerm,
                page: currentReceptorPage,
                perPage: receptorsPerPage
            })
        });

        const data = await response.json();

        if (data.success) {
            console.log('Respuesta del servidor:', data);

            allReceptors = data.data.receptores || data.data;
            totalReceptors = data.data.total || data.total || allReceptors.length;



            renderReceptors();
        } else {
            throw new Error(data.error || 'Error al buscar receptores');
        }

    } catch (error) {
        console.error('Error al buscar receptores:', error);
        mostrarModal({
            title: 'Error',
            message: 'Error al buscar receptores: ' + error.message,
            icon: 'error'
        });
    }
}

function renderReceptors() {
    const tbody = document.getElementById('receptores-tbody');
    tbody.innerHTML = '';

    if (allReceptors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3">No se encontraron resultados</td></tr>';
        return;
    }

    const startIndex = 0;
    const endIndex = allReceptors.length;

    for (let i = startIndex; i < endIndex; i++) {
        const receptor = allReceptors[i];
        const row = document.createElement('tr');

        row.innerHTML = `
            <td>${receptor.NOMBRE_COMPLETO || 'N/A'}</td>
            <td>${receptor.CORREO || 'N/A'}</td>
            <td>
                <button class="select-receptor-btn" data-nombre="${receptor.NOMBRE_COMPLETO}">
                    Seleccionar
                </button>
            </td>
        `;

        tbody.appendChild(row);
    }

    const totalPages = Math.ceil(totalReceptors / receptorsPerPage);
    document.getElementById('page-info').textContent = `Página ${currentReceptorPage} de ${totalPages}`;
    document.getElementById('prev-page').disabled = currentReceptorPage <= 1;
    document.getElementById('next-page').disabled = currentReceptorPage >= totalPages;

    document.querySelectorAll('.select-receptor-btn').forEach(button => {
        button.addEventListener('click', function () {
            const nombreReceptor = this.getAttribute('data-nombre');
            document.getElementById('nombre-receptor').value = nombreReceptor;
            document.getElementById('receptor-modal').style.display = 'none';
        });
    });
}

document.querySelectorAll('.modal .close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function () {
        this.closest('.modal').style.display = 'none';
    });
});

window.addEventListener('click', function (event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
});
