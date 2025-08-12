<div class="module-subnav mb-4">
    <div class="d-flex flex-wrap align-items-center border-bottom pb-3">
        <!-- Título principal -->
        <h5 class="mb-0 me-4">
            <i class="fas fa-truck-loading text-muted me-2"></i>
            Gestión de Compras
        </h5>
        
        <!-- Pestañas Compras -->
        <ul class="nav nav-pills me-4">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'compras.php' ? 'active' : '' ?>" href="compras.php">
                    <i class="fas fa-list-ol me-1"></i> Historial
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'nueva_compra.php' ? 'active' : '' ?>" href="nueva_compra.php">
                    <i class="fas fa-cart-plus me-1"></i> Nueva Compra
                </a>
            </li>
        </ul>
        
        <!-- Separador visual -->
        <div class="vr me-4 d-none d-lg-block"></div>
        
        <!-- Pestañas Proveedores -->
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'proveedores.php' ? 'active' : '' ?>" href="proveedores.php">
                    <i class="fas fa-building me-1"></i> Proveedores
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'nuevo_proveedor.php' ? 'active' : '' ?>" href="nuevo_proveedor.php">
                    <i class="fas fa-plus-circle me-1"></i> Nuevo
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
/* Estilos para el módulo combinado */
.module-subnav {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.module-subnav .nav-pills {
    border-bottom: none;
}

.module-subnav .nav-link {
    color: #6c757d;
    font-weight: 500;
    padding: 0.5rem 1rem;
    margin-right: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.module-subnav .nav-link:hover {
    background: rgba(26, 58, 47, 0.08);
}

.module-subnav .nav-link.active {
    color: white;
    background: var(--primary);
    font-weight: 500;
}

.module-subnav .nav-link i {
    margin-right: 5px;
}

@media (max-width: 768px) {
    .module-subnav .d-flex {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .module-subnav h5 {
        margin-bottom: 10px !important;
    }
}
</style>