<?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
<div class="productos-subnav mb-4">
    <div class="d-flex align-items-center border-bottom pb-2 mb-3">
        <h5 class="mb-0 me-3"><i class="fas fa-boxes text-muted me-2"></i>Productos</h5>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) ==  'productos.php' ? 'active' : '' ?>" href="productos.php">
                    <i class="fas fa-clipboard-list me-1"></i> Catálogo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'agregar_producto.php' ? 'active' : '' ?>" href="agregar_producto.php">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categorias.php' ? 'active' : '' ?>" href="categorias.php">
                    <i class="fas fa-tags me-1"></i> Categorías
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'productos_inactivos.php' ? 'active' : '' ?>" href="productos_inactivos.php">
                    <i class="fas fa-trash-alt me-1"></i> Productos Inactivos
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.productos-subnav {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.productos-subnav .nav-pills {
    border-bottom: none;
}

.productos-subnav .nav-link {
    color: #6c757d;
    font-weight: 500;
    padding: 0.5rem 1rem;
    margin-right: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.productos-subnav .nav-link:hover {
    background: rgba(26, 58, 47, 0.08);
}

.productos-subnav .nav-link.active {
    color: white;
    background: var(--primary);
    font-weight: 500;
}

.productos-subnav .nav-link i {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .productos-subnav .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .productos-subnav h5 {
        margin-bottom: 10px !important;
    }
}
</style>
<?php endif; ?>