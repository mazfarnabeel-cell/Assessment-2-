<?php
session_start();

require_once 'Menu.php';

// Membuat keranjang menggunakan Session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Token keamanan form
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = '';
}

// Daftar menu kantin
$daftarMenu = [
    1 => new Menu(1, 'Nasi Goreng', 15000, 'Makanan'),
    2 => new Menu(2, 'Mie Ayam', 12000, 'Makanan'),
    3 => new Menu(3, 'Es Teh', 5000, 'Minuman'),
    4 => new Menu(4, 'Jus Jeruk', 8000, 'Minuman')
];

// Mengamankan output
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Upload file
function uploadFile(
    $fieldName,
    $targetFolder,
    $allowedMimes,
    $maxSize,
    $label
) {
    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [false, $label . ' belum dipilih.'];
    }

    $upload = $_FILES[$fieldName];

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload gagal.'];
    }

    if ($upload['size'] > $maxSize) {
        return [false, 'Ukuran file terlalu besar.'];
    }

    // Memeriksa tipe file sebenarnya
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMimes[$mime])) {
        return [false, 'Tipe file tidak diizinkan.'];
    }

    // Membuat nama file unik
    $extension = $allowedMimes[$mime];

    $newName = date('Ymd_His') . '_' .
        bin2hex(random_bytes(6)) . '.' . $extension;

    $destination = __DIR__ . '/' .
        $targetFolder . '/' . $newName;

    if (!move_uploaded_file(
        $upload['tmp_name'],
        $destination
    )) {
        return [false, 'File gagal disimpan.'];
    }

    return [true, $newName];
}

// Memproses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Memeriksa token
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $_SESSION['flash'] = 'Permintaan tidak valid.';
        header('Location: index.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // MENAMBAHKAN MENU KE KERANJANG
    if ($action === 'add_to_cart') {

        $menuId = (int)($_POST['menu_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        if (isset($daftarMenu[$menuId]) &&
            $qty >= 1 && $qty <= 20) {

            if (!isset($_SESSION['cart'][$menuId])) {
                $_SESSION['cart'][$menuId] = 0;
            }

            $_SESSION['cart'][$menuId] += $qty;

            $_SESSION['flash'] =
                'Menu berhasil ditambahkan ke keranjang.';
        } else {
            $_SESSION['flash'] = 'Pesanan tidak valid.';
        }

        header('Location: index.php');
        exit;
    }

    // MENGHAPUS MENU
    if ($action === 'remove_item') {

        $menuId = (int)($_POST['menu_id'] ?? 0);

        unset($_SESSION['cart'][$menuId]);

        $_SESSION['flash'] =
            'Menu berhasil dihapus.';

        header('Location: index.php');
        exit;
    }

    // MENGOSONGKAN KERANJANG
    if ($action === 'clear_cart') {

        $_SESSION['cart'] = [];

        $_SESSION['flash'] =
            'Keranjang berhasil dikosongkan.';

        header('Location: index.php');
        exit;
    }

    // FITUR UPLOAD
    if ($action === 'upload') {

        $messages = [];

        // Upload dokumen ke folder files
        if (
            isset($_FILES['document']) &&
            $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $documentMimes = [
                'application/pdf' => 'pdf',
                'text/plain' => 'txt',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    => 'docx'
            ];

            list($ok, $result) = uploadFile(
                'document',
                'files',
                $documentMimes,
                5 * 1024 * 1024,
                'dokumen'
            );

            $messages[] = $ok
                ? 'Dokumen berhasil diupload: ' . $result
                : $result;
        }

        // Upload gambar ke folder images
        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $imageMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            list($ok, $result) = uploadFile(
                'photo',
                'images',
                $imageMimes,
                3 * 1024 * 1024,
                'gambar'
            );

            $messages[] = $ok
                ? 'Gambar berhasil diupload: ' . $result
                : $result;
        }

        if (empty($messages)) {
            $messages[] =
                'Pilih minimal satu dokumen atau gambar.';
        }

        $_SESSION['flash'] = implode(' ', $messages);

        header('Location: index.php');
        exit;
    }
}

// Menghitung total belanja
$total = 0;

foreach ($_SESSION['cart'] as $menuId => $qty) {

    if (isset($daftarMenu[$menuId])) {

        $total +=
            $daftarMenu[$menuId]->getHarga() * $qty;
    }
}

// Mengambil pesan notifikasi
$flash = $_SESSION['flash'];
$_SESSION['flash'] = '';

// Membaca daftar dokumen
$documents = [];

foreach (glob(__DIR__ . '/files/*') as $path) {
    if (is_file($path) && basename($path) !== '.gitkeep') {
        $documents[] = basename($path);
    }
}

// Membaca daftar gambar
$images = [];

foreach (glob(__DIR__ . '/images/*') as $path) {
    if (is_file($path) && basename($path) !== '.gitkeep') {
        $images[] = basename($path);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Sistem Kantin</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            margin: 0;
            color: #243047;
        }

        header {
            background: #173b67;
            color: white;
            padding: 25px;
            text-align: center;
        }

        main {
            max-width: 1000px;
            margin: 25px auto;
            padding: 15px;
        }

        section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px #ddd;
        }

        .menu {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        button {
            background: #1768ac;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 5px;
            cursor: pointer;
        }

        .danger {
            background: #b83b48;
        }

        input {
            padding: 8px;
            margin: 5px;
        }

        .alert {
            background: #e8f5ee;
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        a {
            color: #1768ac;
        }
    </style>
</head>

<body>

<header>
    <h1>Sistem Pemesanan Makanan Kantin</h1>
    <p>Assessment II - Aplikasi Web</p>
</header>

<main>

<?php if ($flash !== ''): ?>
    <div class="alert">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<!-- DAFTAR MENU -->
<section>
    <h2>Daftar Menu Kantin</h2>

    <?php foreach ($daftarMenu as $menu): ?>

        <div class="menu">

            <div>
                <strong>
                    <?= e($menu->getNama()) ?>
                </strong>

                <p>
                    <?= e($menu->getJenis()) ?>
                </p>

                <strong>
                    Rp<?= number_format(
                        $menu->getHarga(),
                        0,
                        ',',
                        '.'
                    ) ?>
                </strong>
            </div>

            <form method="POST">

                <input type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>">

                <input type="hidden"
                    name="action"
                    value="add_to_cart">

                <input type="hidden"
                    name="menu_id"
                    value="<?= $menu->getId() ?>">

                <input type="number"
                    name="quantity"
                    value="1"
                    min="1"
                    max="20">

                <button type="submit">
                    Tambah
                </button>

            </form>

        </div>

    <?php endforeach; ?>
</section>

<!-- KERANJANG SESSION -->
<section>
    <h2>Keranjang Pesanan</h2>

    <p>
        Data pesanan disimpan sementara menggunakan PHP Session.
    </p>

    <?php if (empty($_SESSION['cart'])): ?>

        <p>Keranjang masih kosong.</p>

    <?php else: ?>

        <table>

            <tr>
                <th>Menu</th>
                <th>Jumlah</th>
                <th>Subtotal</th>
                <th>Aksi</th>
            </tr>

            <?php foreach ($_SESSION['cart'] as $menuId => $qty): ?>

                <?php if (!isset($daftarMenu[$menuId])) continue; ?>

                <?php $menu = $daftarMenu[$menuId]; ?>

                <tr>

                    <td>
                        <?= e($menu->getNama()) ?>
                    </td>

                    <td>
                        <?= (int)$qty ?>
                    </td>

                    <td>
                        Rp<?= number_format(
                            $menu->getHarga() * $qty,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>
                        <form method="POST">

                            <input type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>">

                            <input type="hidden"
                                name="action"
                                value="remove_item">

                            <input type="hidden"
                                name="menu_id"
                                value="<?= (int)$menuId ?>">

                            <button class="danger">
                                Hapus
                            </button>

                        </form>
                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

        <h3>
            Total: Rp<?= number_format(
                $total,
                0,
                ',',
                '.'
            ) ?>
        </h3>

        <form method="POST">

            <input type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>">

            <input type="hidden"
                name="action"
                value="clear_cart">

            <button class="danger">
                Kosongkan Keranjang
            </button>

        </form>

    <?php endif; ?>

</section>

<!-- UPLOAD FILE DAN GAMBAR -->
<section>

    <h2>Upload Dokumen dan Gambar</h2>

    <form method="POST"
        enctype="multipart/form-data">

        <input type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>">

        <input type="hidden"
            name="action"
            value="upload">

        <h3>Upload Dokumen</h3>

        <input type="file"
            name="document"
            accept=".pdf,.txt,.doc,.docx">

        <h3>Upload Gambar</h3>

        <input type="file"
            name="photo"
            accept="image/jpeg,image/png,image/gif,image/webp">

        <br><br>

        <button type="submit">
            Upload File
        </button>

    </form>

</section>

<!-- DAFTAR DOKUMEN -->
<section>

    <h2>Dokumen Tersimpan (Folder files)</h2>

    <?php if (empty($documents)): ?>

        <p>Belum ada dokumen.</p>

    <?php else: ?>

        <?php foreach ($documents as $doc): ?>

            <p>
                <a href="files/<?= rawurlencode($doc) ?>"
                    target="_blank">
                    <?= e($doc) ?>
                </a>
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

<!-- DAFTAR GAMBAR -->
<section>

    <h2>Gambar Tersimpan (Folder images)</h2>

    <?php if (empty($images)): ?>

        <p>Belum ada gambar.</p>

    <?php else: ?>

        <?php foreach ($images as $img): ?>

            <p>
                <a href="images/<?= rawurlencode($img) ?>"
                    target="_blank">
                    <?= e($img) ?>
                </a>
            </p>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

</main>

</body>
</html>