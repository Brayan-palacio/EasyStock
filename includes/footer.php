</main> <!-- Cierra el contenido principal -->
        </div> <!-- Cierra el contenedor general -->

        <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery (para animaciones y efectos) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const currentPage = window.location.pathname.split('/').pop();
    const navButtons = document.querySelectorAll('.d-flex.flex-wrap a.btn');
    
    navButtons.forEach(button => {
        const buttonPage = button.getAttribute('href').split('/').pop();
        if (buttonPage === currentPage) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
    
    const submenus = document.querySelectorAll('.nav-item > a[data-bs-toggle="collapse"]');
    submenus.forEach(submenu => {
        submenu.addEventListener('click', function() {
            submenus.forEach(sm => {
                if (sm !== submenu) {
                    const target = sm.getAttribute('data-bs-target');
                    const collapse = bootstrap.Collapse.getInstance(target);
                    if (collapse) {
                        collapse.hide();
                    }
                }
            });
        });
    });
});
        </script>
    </body>
</html>
