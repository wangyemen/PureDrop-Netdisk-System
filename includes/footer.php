    <footer style="background: white; padding: 20px; text-align: center; color: #999; font-size: 14px; margin-top: 40px;">
        <p>&copy; <?php echo date('Y'); ?> PureDrop网盘. All rights reserved.</p>
    </footer>
    
    <script>
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    </script>
</body>
</html>