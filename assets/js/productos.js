document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.btn-dinamico').forEach(button => {
      button.addEventListener('click', function (e) {
        e.preventDefault();
        const url = this.getAttribute('data-url');
        if (url) {
          window.location.href = url;
        }
      });
    });
  });
  
  function eliminarProducto(id) {
    // Aquí iría la lógica para eliminar el producto, por ejemplo, usando fetch o AJAX
    console.log(`Producto con ID ${id} eliminado (simulación).`);
  }
  

// Función para filtrar las filas de la tabla según el texto ingresado
function filtrarTabla() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const tabla = document.getElementById('tablaProductos');
    const rows = tabla.getElementsByTagName('tr');

    // Iterar sobre todas las filas de la tabla y ocultar las que no coincidan con la búsqueda
    for (let i = 1; i < rows.length; i++) { // Empezar desde 1 para evitar la fila del encabezado
        const cells = rows[i].getElementsByTagName('td');
        let filaCoincide = false;

        // Recorremos cada celda de la fila para comprobar si contiene el texto buscado
        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                if (cell.textContent.toLowerCase().includes(filter)) {
                    filaCoincide = true;
                    break;
                }
            }
        }

        // Si la fila coincide con el filtro, mostrarla, sino ocultarla
        rows[i].style.display = filaCoincide ? '' : 'none';
    }
}


// Ocultar mensaje de éxito después de 3 segundos
if (window.location.search.includes('mensaje')) {
  setTimeout(() => {
      window.history.replaceState({}, document.title, window.location.pathname);
  }, 3000);
}

// Confirmación para eliminar productos con SweetAlert2
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".btn-eliminar").forEach(button => {
      button.addEventListener("click", function () {
          const id = this.getAttribute("data-id");

          Swal.fire({
              title: "¿Estás seguro?",
              text: "¡Este producto se eliminará permanentemente!",
              icon: "warning",
              showCancelButton: true,
              confirmButtonColor: "#d33",
              cancelButtonColor: "#3085d6",
              confirmButtonText: "Sí, eliminar",
              cancelButtonText: "Cancelar"
          }).then((result) => {
              if (result.isConfirmed) {
                  window.location.href = `eliminar_producto.php?id=${id}`;
              }
          });
      });
  });
});

// Filtrar tabla
function filtrarTabla() {
  const input = document.getElementById("searchInput");
  const filter = input.value.toUpperCase();
  const table = document.getElementById("tablaProductos");
  const rows = table.getElementsByTagName("tr");

  for (let i = 1; i < rows.length; i++) {
      const cells = rows[i].getElementsByTagName("td");
      let match = false;
      for (let j = 0; j < cells.length; j++) {
          if (cells[j].textContent.toUpperCase().indexOf(filter) > -1) {
              match = true;
              break;
          }
      }
      rows[i].style.display = match ? "" : "none";
  }
}