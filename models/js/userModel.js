document.addEventListener('DOMContentLoaded', () => {
    const userTableBody = document.getElementById('user-table-body');
    const addUserForm = document.getElementById('add-user-form');
    const editUserForm = document.getElementById('edit-user-form');

    // Recuperar datos del usuario desde sessionStorage
    const user = JSON.parse(sessionStorage.getItem("user"));

    // Redirigir si no hay usuario en sessionStorage
    if (!user) {
        alert("No tienes una sesión activa. Serás redirigido al login.");
        window.location.href = "./login.html";
    } else if (user.estatus !== 'A') {
        alert("Tu cuenta está inactiva. Contacta al administrador.");
        window.location.href = "./login.html";
    }

    const userModel = {
        async addUser(user) {
            try {
                const response = await fetch('../controllers/UserController.php?action=addUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(user),
                });
                return await response.json();
            } catch (error) {
                console.error("Error al agregar usuario:", error);
                return { success: false, message: "Error al agregar usuario." };
            }
        },
        async getAllUsers() {
            try {
                const response = await fetch('../controllers/UserController.php?action=getAllUsers');
                const result = await response.json();
                if (result.success) {
                    return result.data;
                } else {
                    console.error(result.message);
                    return [];
                }
            } catch (error) {
                console.error("Error al obtener usuarios:", error);
                return [];
            }
        },
        async getUser(id) {
            try {
                const response = await fetch(`../controllers/UserController.php?action=getUser&id=${id}`);
                return await response.json();
            } catch (error) {
                console.error("Error al obtener usuario:", error);
                return { success: false, message: "Error al obtener usuario." };
            }
        },
        async updateUser(user) {
            try {
                const response = await fetch('../controllers/UserController.php?action=updateUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(user),
                });
                return await response.json();
            } catch (error) {
                console.error("Error al actualizar usuario:", error);
                return { success: false, message: "Error al actualizar usuario." };
            }
        },
        async deleteUser(id) {
            try {
                const response = await fetch('../controllers/UserController.php?action=deleteUser', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                return await response.json();
            } catch (error) {
                console.error("Error al eliminar usuario:", error);
                return { success: false, message: "Error al eliminar usuario." };
            }
        },
        async eliminarUsuariosSeleccionados() {
            console.log("Función eliminarUsuariosSeleccionados llamada");
            if (window.confirm("¿Está seguro de que desea eliminar los usuarios seleccionados?")) {
                const checkboxes = document.querySelectorAll('.chkusuario:checked');
                const seleccionados = Array.from(checkboxes).map(checkbox => checkbox.id);
        
                console.log("Usuarios seleccionados para eliminar:", seleccionados);
        
                if (seleccionados.length > 0) {
                    try {
                        const response = await fetch('../controllers/UserController.php?action=deleteUsers', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ DELETE_USUARIOS_ID: seleccionados })
                        });
        
                        const result = await response.json();
                        console.log("Respuesta del servidor:", result);
        
                        if (result.success) {
                            alert("Usuarios eliminados exitosamente.");
                            $('#userTable').DataTable().destroy();
                            await loadUsers();
                        } else {
                            alert("Error al eliminar usuarios: " + result.message);
                        }
                    } catch (error) {
                        console.error("Error al eliminar usuarios:", error);
                        alert("Ocurrió un error al intentar eliminar los usuarios.");
                    }
                } else {
                    alert("No se ha seleccionado ningún usuario.");
                }
            }
        },        
    };

    /**
     * Vista: Funciones para manejar la interfaz de usuario.
     */
    const renderUsers = (usuarios) => {
        userTableBody.innerHTML = '';
        usuarios.forEach((user) => {
            // Determinar el icono y color basado en el estatus
            const icono = user.estatus === 'A' 
                ? '<i class="bi bi-circle-fill text-success" title="Usuario habilitado"></i>' 
                : '<i class="bi bi-circle-fill text-danger" title="Usuario deshabilitado"></i>';
            
            // Determinar el estilo de la fila basado en el estatus
            const rowClass = user.estatus === 'A' ? '' : 'table-danger'; // Clase Bootstrap para resaltar en rojo
    
            // Crear la fila con los datos del usuario
            const tr = document.createElement('tr');
            tr.className = rowClass; // Asignar la clase al <tr>
            tr.innerHTML = `
                <td style="text-align: center;">
                    <input type="checkbox" class="chkusuario"  data-id="${user.usuario_id}" data-status="${user.estatus}">
                </td>
                <td>${user.nombre}</td>
                <td>${user.email}</td>
                <td>${user.rol}</td>
                <td class="text-center">
                    <button class="btn btn-link text-primary" data-id="${user.usuario_id}" data-action="edit" title="Editar">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                </td>
                <td class="text-center">
                    <button class="btn btn-link toggle-status" data-id="${user.usuario_id}" data-status="${user.estatus}">
                        ${icono}
                    </button>
                </td>
            `;
            userTableBody.appendChild(tr);
        });
    };
    
    
    
    const initializeDataTable = () => {
        // Verifica si el DataTable ya está inicializado
        if ($.fn.DataTable.isDataTable('#userTable')) {
            $('#userTable').DataTable().destroy(); // Destruye la instancia existente
        }
    
        // Inicializa el DataTable nuevamente
        $('#userTable').DataTable({
            paging: true,
            searching: true,
            ordering: true,
            language: {
                lengthMenu: "Mostrar _MENU_ registros por página",
                zeroRecords: "No se encontraron resultados",
                info: "Mostrando página _PAGE_ de _PAGES_",
                infoEmpty: "No hay datos disponibles",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                search: "Buscar:",
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                },
            },
        });
    };

    // Función para seleccionar/deseleccionar todos los checkboxes
    $('#selectAll').on('click', function() {
        var isChecked = $(this).prop('checked');
        $('.chkusuario').each(function() {
            $(this).prop('checked', isChecked);
        });
    });
    
    const loadUsers = async () => {
        userTableBody.innerHTML = '<tr><td colspan="6" class="text-center">Cargando...</td></tr>';
        const usuarios = await userModel.getAllUsers();
        renderUsers(usuarios);  
        initializeDataTable();
    };
    
    /**
     * Eventos
     */
    addUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(addUserForm);
        const user = {
            nombre: formData.get('nombre').trim(),
            email: formData.get('email').trim(),
            password: formData.get('password'),
            rol: formData.get('rol'),
        };

        if (!user.nombre || !user.email || !user.password || !user.rol) {
            alert("Todos los campos son obligatorios.");
            return;
        }

        const result = await userModel.addUser(user);
        alert(result.message);

        if (result.success) {
            addUserForm.reset();
            const addUserModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
            addUserModal.hide();
            $('#userTable').DataTable().destroy();
            loadUsers();
        }
    });

    document.addEventListener('click', async (event) => {
        // Editar usuario
        if (event.target.closest('button[data-action="edit"]')) {
            const id = event.target.closest('button').getAttribute('data-id');
            const result = await userModel.getUser(id);
    
            if (result.success) {
                const user = result.data;
    
                document.getElementById('edit-id').value = user.usuario_id;
                document.getElementById('edit-nombre').value = user.nombre;
                document.getElementById('edit-email').value = user.email;
                document.getElementById('edit-rol').value = user.rol;
    
                const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                editUserModal.show();
            } else {
                alert(result.message);
            }
        }
    
        // Eliminar usuario
        /*if (event.target.closest('button[data-action="delete"]')) {
            const id = event.target.closest('button').getAttribute('data-id');
            const confirmation = confirm("¿Estás seguro de que deseas eliminar este usuario?");
        
            if (confirmation) {
                try {
                    const response = await fetch('../controllers/UserController.php?action=deleteUser', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id }),
                    });
    
                    const result = await response.json();
                    alert(result.message);
    
                    if (result.success) {
                        $('#userTable').DataTable().destroy();
                        loadUsers();
                    }
                } catch (error) {
                    console.error("Error al eliminar usuario:", error);
                    alert("Error al intentar eliminar usuario.");
                }
            }
        }*/
    
        // Cambiar estatus de usuario
        if (event.target.closest('button.toggle-status')) {
            const toggleButton = event.target.closest('button.toggle-status');
            const userId = toggleButton.getAttribute('data-id');
            const currentStatus = toggleButton.getAttribute('data-status');
            const newStatus = currentStatus === 'A' ? 'B' : 'A';
    
            if (confirm(`¿Estás seguro de que deseas cambiar el estatus de este usuario?`)) {
                try {
                    const response = await fetch('../controllers/UserController.php?action=updateStatus', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: userId, estatus: newStatus }),
                    });
    
                    const result = await response.json();
                    alert(result.message);
    
                    if (result.success) {
                        $('#userTable').DataTable().destroy();
                        loadUsers();
                    }
                } catch (error) {
                    console.error("Error al cambiar el estatus:", error);
                    alert("Error al intentar cambiar el estatus.");
                }
            }
        }
    });

    const toggleStatusButton = document.getElementById('toggle-status');
    toggleStatusButton.addEventListener('click', async () => {
        const selectedCheckboxes = document.querySelectorAll('.chkusuario:checked');

        if (selectedCheckboxes.length === 0) {
            alert("Por favor, selecciona al menos un usuario.");
            return;
        }

        const usuariosSeleccionados = Array.from(selectedCheckboxes).map(checkbox => ({
            id: checkbox.getAttribute('data-id'),
            estatus: checkbox.getAttribute('data-status') === 'A' ? 'B' : 'A' // Cambia el estatus
        }));

        if (confirm(`¿Estás seguro de que deseas cambiar el estado de ${usuariosSeleccionados.length} usuario(s)?`)) {
            try {
                const response = await fetch('../controllers/UserController.php?action=updateStatusBox', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ usuarios: usuariosSeleccionados }),
                });

                const result = await response.json();

                if (result.success) {
                    alert("Estatus de usuario(s) actualizado exitosamente.");
                    $('#userTable').DataTable().destroy(); // Reinicia DataTable
                    await loadUsers(); // Recarga la tabla
                } else {
                    alert("Error al actualizar el estatus de los usuarios: " + result.message);
                }
            } catch (error) {
                console.error("Error al actualizar el estatus de los usuarios:", error);
                alert("Ocurrió un error al intentar cambiar el estatus de los usuarios.");
            }
        }
    });

    

    editUserForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(editUserForm);
        const user = {
            id: formData.get('id'),
            nombre: formData.get('nombre').trim(),
            email: formData.get('email').trim(),
            rol: formData.get('rol'),
        };
    
        if (!user.id || !user.nombre || !user.email || !user.rol) {
            alert("Todos los campos son obligatorios.");
            return;
        }
    
        const result = await userModel.updateUser(user);
        alert(result.message);
    
        if (result.success) {
            const editUserModal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            editUserModal.hide();

            await loadUsers();
        }
    });
    

    // Cerrar sesión
    document.getElementById("logout").addEventListener("click", async () => {
        try {
            // Cambiar el endpoint para que llame al controlador de logout
            const response = await fetch("../controllers/LoginController.php?action=logout", {
                method: "POST"
            });
            if (response.ok) {
                sessionStorage.removeItem("user");
                window.location.href = "./login.html";
            } else {
                alert("No se pudo cerrar la sesión. Intenta de nuevo.");
            }
        } catch (error) {
            alert("Error al cerrar la sesión: " + error.message);
        }
    });

    // Inicializar vista
    loadUsers();
});
