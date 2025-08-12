<?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
<div class="inventario-subnav mb-4">
    <div class="d-flex align-items-center border-bottom pb-2 mb-3">
        <h5 class="mb-0 me-2"><i class="fas fa-warehouse text-muted me-2"></i>Inventario</h5>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'agregar_inventario.php' ? 'active' : '' ?>" href="agregar_inventario.php">
                    <i class="fas fa-arrow-down me-1"></i> Agregar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ajustes_inventario.php' ? 'active' : '' ?>" href="ajustes_inventario.php">
                    <i class="fas fa-exchange-alt me-1"></i> Ajustes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'productos_bajos.php' ? 'active' : '' ?>" href="productos_bajos.php">
                    <i class="fas fa-exclamation-triangle me-1"></i> Bajos Stock
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'informe_inventario.php' ? 'active' : '' ?>" href="informe_inventario.php">
                    <i class="fas fa-file-alt me-1"></i> Informe
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'informe_movimientos.php' ? 'active' : '' ?>" href="informe_movimientos.php">
                    <i class="fas fa-tasks me-1"></i> Movimientos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'kardex.php' ? 'active' : '' ?>" href="kardex.php">
                    <i class="fas fa-history me-1"></i> Kardex
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.inventario-subnav {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.inventario-subnav .nav-pills {
    border-bottom: none;
}

.inventario-subnav .nav-link {
    color: #6c757d;
    font-weight: 500;
    padding: 0.5rem 1rem;
    margin-right: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.inventario-subnav .nav-link:hover {
    background: rgba(26, 58, 47, 0.08);
}

.inventario-subnav .nav-link.active {
    color: white;
    background: var(--primary);
    font-weight: 500;
}

.inventario-subnav .nav-link i {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .inventario-subnav .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .inventario-subnav h5 {
        margin-bottom: 10px !important;
    }
}
</style>
<?php endif; ?>