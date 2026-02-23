// assets/js/realtime.js
const socket = io("http://localhost:3000");

socket.on("stock_update", (data) => {
    console.log("Mise à jour stock reçue:", data);
    // If we are on the booking page, refresh availability
    if (typeof updateLiveSummary === 'function') {
        updateLiveSummary();
    }
});

socket.on("new_booking", (data) => {
    // Show a toast or notification for admins
    if (window.location.pathname.includes('admin')) {
        alert("Nouvelle réservation reçue : #" + data.id);
    }
});
