// async function checkUsername() {
//     const username = document.getElementById("username").value;
//     if (!username) {
//         alert("Please enter a username.");
//         return;
//     }

//     const response = await fetch("/uniqueusername", {
//         method: "POST",
//         headers: { "Content-Type": "application/json" },
//         body: JSON.stringify({ username })
//     });

//     const data = await response.json();
//     document.getElementById("usernameStatus").innerText = data.unique 
//         ? "Username is available" 
//         : "Username is taken";
// }

// async function registerUser() {
//     const username = document.getElementById("username").value;
//     const email = document.getElementById("email").value;
//     const password = document.getElementById("password").value;

//     if (!username || !email || !password) {
//         alert("Please fill all fields.");
//         return;
//     }

//     const response = await fetch("/register", {
//         method: "POST",
//         headers: { "Content-Type": "application/json" },
//         body: JSON.stringify({ username, email, password })
//     });

//     const data = await response.json();
//     alert(data.message);
// }

// async function loginUser() {
//     const username = document.getElementById("loginUsername").value;
//     const password = document.getElementById("loginPassword").value;

//     if (!username || !password) {
//         alert("Please enter username and password.");
//         return;
//     }

//     const response = await fetch("/login", {
//         method: "POST",
//         headers: { "Content-Type": "application/json" },
//         body: JSON.stringify({ username, password })
//     });

//     const data = await response.json();
//     alert(data.message);
// }
// Switch between Register and Login
function toggleForm() {
    let title = document.getElementById("formTitle");
    if (title.innerText === "Register") {
        title.innerText = "Login";
        document.querySelector("button[onclick='register()']").setAttribute("onclick", "login()");
    } else {
        title.innerText = "Register";
        document.querySelector("button[onclick='login()']").setAttribute("onclick", "register()");
    }
}

// Register User
function register() {
    let username = document.getElementById("username").value;
    let email = document.getElementById("email").value;
    let password = document.getElementById("password").value;

    fetch("/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, email, password })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) window.location.href = "edit-profile.html";
    });
}

// Login User
function login() {
    let username = document.getElementById("username").value;
    let password = document.getElementById("password").value;

    fetch("/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ username, password })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) window.location.href = "edit-profile.html";
    });
}

// Update Bio
function updateBio() {
    let bio = document.getElementById("bio").value;
    if (bio.length > 100) {
        alert("Bio should be 100 characters or less!");
        return;
    }

    fetch("/bio", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bio })
    })
    .then(response => response.json())
    .then(data => alert(data.message));
}

// Update Profile Picture
function updateProfilePic() {
    let file = document.getElementById("profilePicInput").files[0];
    if (!file) {
        alert("Please select an image!");
        return;
    }

    let reader = new FileReader();
    reader.onloadend = function() {
        let base64String = reader.result.split(",")[1];

        fetch("/profilepic", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ image: base64String })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            document.getElementById("profilePicPreview").src = reader.result;
            document.getElementById("profilePicPreview").style.display = "block";
        });
    };
    reader.readAsDataURL(file);
}

// Logout
// function logout() {
//     window.location.href = "index.html";
// }
function logout() {
    fetch("/logout", { method: "POST" })  // Server-side session clear
    .then(() => {
        localStorage.clear(); // Clear client-side storage if needed
        sessionStorage.clear();
        window.location.href = "index.html"; // Redirect to home/login page
    });
}

document.addEventListener("DOMContentLoaded", function () {
    let username = localStorage.getItem("username");
    if (username) {
        document.getElementById("fromUser").value = username;
    } else {
        alert("User not logged in!");
        window.location.href = "index.html";
    }
});

// Send Money
function transferMoney() {
    let from = document.getElementById("fromUser").value;
    let to = document.getElementById("toUser").value.trim();
    let quantity = document.getElementById("amount").value.trim();
    let comments = document.getElementById("comments").value.trim();

    if (!to || !quantity) {
        alert("Receiver username and amount are required!");
        return;
    }

    fetch("/moneytransfer", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ from, to, quantity, comments })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.href = "edit-profile.html"; // Redirect to profile page after transfer
        }
    });
}






