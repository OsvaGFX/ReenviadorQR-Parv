document.getElementById("loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();

    try {
        const response = await fetch("../controllers/LoginController.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({ email, password }),
        });

        
        const data = await response.json();
        console.log(data.error);
        if (data.success) {
            // Almacenar información del usuario y redirigir
            sessionStorage.setItem("user", JSON.stringify(data.data));
            window.location.href = "./home.html"; // Cambiar a la página de inicio
        } else {
            alert(data.message || "Credenciales incorrectas.");
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Error de conexión: " + error.message);
        //alert(data.body);
    }
});
