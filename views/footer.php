<?php
/**
 * views/footer.php - Template Footer Aplikasi
 * Mini Project 3: Relational Database + Advanced PHP
 * SweetBakes — Artisan Bakery & Pastry Studio Manager
 * Mahasiswa: Nadya Salsabila Zahra (NIM: 250180031)
 */
?>
    </main>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> <strong>SweetBakes: Artisan Pastry & Cake Studio Manager</strong></p>
        <p style="margin-top: 4px; font-size: 0.8rem;">
            Nadya Salsabila Zahra &bull; NIM: 250180031 &bull; Pemrograman Web (Relational Database + Advanced PHP)
        </p>
    </footer>

    <script>
        // Dual Theme LocalStorage Controller
        const themeToggle = document.getElementById('themeToggle');
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            if (themeToggle) themeToggle.textContent = '☀️';
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    themeToggle.textContent = '🌙';
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    themeToggle.textContent = '☀️';
                }
            });
        }
    </script>
</body>
</html>
