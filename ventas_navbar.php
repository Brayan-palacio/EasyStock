<?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor', 'Consulta'])): ?>
<div class="ventas-subnav mb-4">
    <div class="d-flex align-items-center border-bottom pb-2 mb-3">
        <h5 class="mb-0 me-3"><i class="fas fa-cash-register text-muted me-2"></i>Ventas</h5>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ventas.php' ? 'active' : '' ?>" href="ventas.php">
                    <i class="fas fa-list-ul me-1"></i> Historial
                </a>
            </li>
            <?php if(tienePermiso(['Administrador', 'Usuario', 'Supervisor'])): ?>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'nueva_venta.php' ? 'active' : '' ?>" href="nueva_venta.php">
                    <i class="fas fa-receipt me-1"></i> Nueva Venta
                </a>
            </li>
            <?php if(tienePermiso(['Administrador', 'Supervisor'])): ?>
            <!-- Reportes -->
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : '' ?>" href="reportes.php">
                    <i class="fas fa-chart-pie me-1"></i> Reportes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'estadisticas.php' ? 'active' : '' ?>" href="estadisticas.php">
                    <i class="fas fa-chart-line me-1"></i> Estadísticas
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'listar_cotizaciones.php' ? 'active' : '' ?>" href="listar_cotizaciones.php">
                    <i class="fas fa-file-invoice-dollar me-1"></i> Cotizaciones
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<style>
.ventas-subnav {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.ventas-subnav .nav-pills {
    border-bottom: none;
}

.ventas-subnav .nav-link {
    color: #6c757d;
    font-weight: 500;
    padding: 0.5rem 1rem;
    margin-right: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.ventas-subnav .nav-link:hover {
    background: rgba(26, 58, 47, 0.08);
}

.ventas-subnav .nav-link.active {
    color: white;
    background: var(--primary);
    font-weight: 500;
}

.ventas-subnav .nav-link i {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .ventas-subnav .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .ventas-subnav h5 {
        margin-bottom: 10px !important;
    }
}
</style>
<?php endif; ?>