
document.addEventListener("DOMContentLoaded", () => {
const formularioEdicion = document.getElementById("formularioEdicion");
const tablaProductosContainer = document.getElementById("tablaProductosContainer");

// Delegación de eventos para los botones "Editar"
document.body.addEventListener("click", (event) => {
    if (event.target.classList.contains("btn-editar")) {
        const boton = event.target;
        const idProducto = boton.getAttribute("data-id");

        // Ocultar la tabla de productos
        tablaProductosContainer.style.display = "none";

        // Realiza una petición AJAX para obtener los datos del producto
        fetch(`obtener_producto.php?id=${idProducto}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Error al obtener datos del producto");
                }
                return response.json();
            })
            .then(data => {
                if (!data || !data.id) {
                    throw new Error("El producto no existe o los datos son inválidos.");
                }

                // Generar dinámicamente el formulario con los datos obtenidos
                formularioEdicion.innerHTML = `
                    <div class="container mt-4">
                        <h4>Editar Producto</h4>
                        <div class="barra-azul"></div>
                        <form method="POST" action="actualizar_producto.php">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="row mb-3">
                                <div class="col-md-12 form-group">
                                    <i class="fa fa-cube form-control-icon"></i>
                                    <label for="descripcion" class="form-label">Descripción</label>
                                    <input type="text" class="form-control" id="descripcion" name="descripcion" value="${data.descripcion}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="categoria_id" class="form-label">Categoría</label>
                                <select class="form-select" id="categoria_id" name="categoria_id">
                                    <option value="">Sin Categoría</option>
                                    ${data.categorias.map(cat => `
                                        <option value="${cat.id}" ${cat.id == data.categoria_id ? 'selected' : ''}>
                                            ${cat.nombre}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 form-group">
                                    <label for="cantidad" class="form-label">Cantidad</label>
                                    <input type="number" class="form-control" id="cantidad" name="cantidad" value="${data.cantidad}" required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="precio_compra" class="form-label">Precio de compra</label>
                                    <input type="number" class="form-control" id="precio_compra" name="precio_compra" value="${data.precio_compra}" required>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label for="precio_venta" class="form-label">Precio de venta</label>
                                    <input type="number" class="form-control" id="precio_venta" name="precio_venta" value="${data.precio_venta}" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">Guardar Cambios</button>
                            <button type="button" class="btn btn-secondary" onclick="cerrarFormulario()">Cancelar</button>
                        </form>
                    </div>`;
                
                // Mostrar el formulario de edición
                formularioEdicion.style.display = "block";
            })
            .catch(error => {
                console.error("Error al obtener los datos del producto:", error);
                alert("Hubo un error al intentar cargar los datos del producto. Por favor, inténtelo de nuevo.");
            });
    }
});
});

// Función para cerrar el formulario y mostrar nuevamente la tabla
function cerrarFormulario() {
const formularioEdicion = document.getElementById("formularioEdicion");
const tablaProductosContainer = document.getElementById("tablaProductosContainer");

// Ocultar formulario de edición
formularioEdicion.style.display = "none";

// Mostrar la tabla de productos nuevamente
tablaProductosContainer.style.display = "block";
}