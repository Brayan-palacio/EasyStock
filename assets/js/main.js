// Actualización del reloj dinámico
function actualizarReloj() {
    const ahora = new Date();
    const fecha = ahora.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const hora = ahora.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('reloj').innerText = `${fecha} ${hora}`;
}
setInterval(actualizarReloj, 1000);
actualizarReloj();