document.addEventListener("DOMContentLoaded", () => {
    // Recuperar datos del usuario desde sessionStorage
    const user = JSON.parse(sessionStorage.getItem("user"));

    // Redirigir si no hay usuario en sessionStorage
    if (!user) {
        alert("No tienes una sesión activa. Serás redirigido al login.");
        window.location.href = "./login.html";
        return;
    }

  

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
});
