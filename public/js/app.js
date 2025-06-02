document.addEventListener("DOMContentLoaded", () => {
    const user = JSON.parse(sessionStorage.getItem("user"));

    // Verificar si el usuario está autenticado
    if (!user) {
        alert("No tienes una sesión activa. Serás redirigido al login.");
        window.location.href = "/views/login.php";
        return;
    }

    // Mostrar el nombre del usuario en la página
    document.getElementById("user-data").textContent = `Hola, ${user.nombre}`;

    // Manejar el cierre de sesión
    document.getElementById("logout").addEventListener("click", async () => {
        // Cerrar sesión en el servidor
        await fetch("../public/index.php?action=logout");
        // Eliminar los datos del usuario en sessionStorage
        sessionStorage.removeItem("user");
        // Redirigir al login
        window.location.href = "/views/login.php";
    });
});
