<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Rodapé do layout administrativo (fecha as tags abertas no header).
 *
 * @var array $app
 */
$baseUrl = $app['base_url'] ?? '';
?>
        </main>
    </div>
</div>
<script src="<?= View::e(View::assetVersioned('js/admin.js', $baseUrl)) ?>" defer></script>
</body>
</html>
