<?php
/**
 * PERSONNALY - Modèle MediaLibrary
 * Médiathèque centrale
 */

require_once __DIR__ . '/../core/Database.php';

class MediaLibrary
{
    private $db;
    private $uploadDir;
    private $uploadUrl;

    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'webm', 'pdf'];
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->uploadDir = __DIR__ . '/../../public/uploads/media/';
        $this->uploadUrl = '/uploads/media/';
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM media_library WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $media = $stmt->fetch();
        if ($media) {
            $media['tags'] = json_decode($media['tags'] ?? '[]', true);
        }
        return $media ?: null;
    }

    public function findAll(?string $folder = null, ?string $mimeType = null, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM media_library WHERE 1=1';
        $params = [];

        if ($folder) {
            $sql .= ' AND folder = ?';
            $params[] = $folder;
        }

        if ($mimeType) {
            if (strpos($mimeType, '/') === false) {
                $sql .= ' AND mime_type LIKE ?';
                $params[] = $mimeType . '/%';
            } else {
                $sql .= ' AND mime_type = ?';
                $params[] = $mimeType;
            }
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $medias = $stmt->fetchAll();

        foreach ($medias as &$media) {
            $media['tags'] = json_decode($media['tags'] ?? '[]', true);
        }

        return $medias;
    }

    public function search(string $query, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM media_library
             WHERE original_name LIKE ? OR alt_text LIKE ? OR folder LIKE ?
             ORDER BY created_at DESC
             LIMIT ?'
        );
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    public function getFolders(): array
    {
        $stmt = $this->db->query('SELECT DISTINCT folder FROM media_library ORDER BY folder');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Upload un fichier
     */
    public function upload(array $file, array $options = []): ?array
    {
        // Vérifications
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur upload: ' . $this->getUploadErrorMessage($file['error']));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception('Fichier trop volumineux (max 10MB)');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            throw new Exception('Extension non autorisée: ' . $ext);
        }

        // Créer le dossier si nécessaire
        $folder = $options['folder'] ?? 'general';
        $targetDir = $this->uploadDir . $folder . '/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Générer un nom unique
        $filename = uniqid() . '_' . time() . '.' . $ext;
        $targetPath = $targetDir . $filename;

        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Impossible de déplacer le fichier');
        }

        // Infos image
        $width = null;
        $height = null;
        $thumbnailUrl = null;

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $imageInfo = getimagesize($targetPath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }

            // Créer miniature si grande image
            if ($width > 400 || $height > 400) {
                $thumbnailUrl = $this->createThumbnail($targetPath, $folder, $filename);
            }
        }

        // Convertir en WebP si image
        if (in_array($ext, ['jpg', 'jpeg', 'png']) && class_exists('ImageHelper')) {
            require_once __DIR__ . '/../helpers/ImageHelper.php';
            ImageHelper::convertToWebP($targetPath);
        }

        // Enregistrer en BDD
        $url = $this->uploadUrl . $folder . '/' . $filename;
        $mimeType = $file['type'] ?: mime_content_type($targetPath);

        $stmt = $this->db->prepare(
            'INSERT INTO media_library
             (filename, original_name, mime_type, file_size, width, height, url, thumbnail_url, alt_text, folder, tags, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $filename,
            $file['name'],
            $mimeType,
            $file['size'],
            $width,
            $height,
            $url,
            $thumbnailUrl,
            $options['alt_text'] ?? null,
            $folder,
            json_encode($options['tags'] ?? []),
            $options['uploaded_by'] ?? null
        ]);

        return $this->findById((int) $this->db->lastInsertId());
    }

    /**
     * Upload multiple fichiers
     */
    public function uploadMultiple(array $files, array $options = []): array
    {
        $results = [];

        // Réorganiser le tableau $_FILES
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            try {
                $results[] = [
                    'success' => true,
                    'media' => $this->upload($file, $options)
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'filename' => $file['name']
                ];
            }
        }

        return $results;
    }

    /**
     * Crée une miniature
     */
    private function createThumbnail(string $sourcePath, string $folder, string $filename): ?string
    {
        $thumbDir = $this->uploadDir . $folder . '/thumbs/';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $thumbPath = $thumbDir . $filename;
        $maxSize = 200;

        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mime = $imageInfo['mime'];

        // Calculer dimensions
        if ($sourceWidth > $sourceHeight) {
            $newWidth = $maxSize;
            $newHeight = (int) ($sourceHeight * $maxSize / $sourceWidth);
        } else {
            $newHeight = $maxSize;
            $newWidth = (int) ($sourceWidth * $maxSize / $sourceHeight);
        }

        // Créer image source
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return null;
        }

        if (!$source) return null;

        // Créer miniature
        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        // Préserver transparence PNG
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        // Sauvegarder
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($thumb, $thumbPath, 80);
                break;
            case 'image/png':
                imagepng($thumb, $thumbPath, 8);
                break;
            case 'image/gif':
                imagegif($thumb, $thumbPath);
                break;
            case 'image/webp':
                imagewebp($thumb, $thumbPath, 80);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumb);

        return $this->uploadUrl . $folder . '/thumbs/' . $filename;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];
        $allowedFields = ['alt_text', 'folder'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $values[] = $data[$field];
            }
        }

        if (array_key_exists('tags', $data)) {
            $fields[] = '`tags` = ?';
            $values[] = json_encode($data['tags']);
        }

        if (empty($fields)) return false;

        $values[] = $id;
        $sql = 'UPDATE media_library SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $media = $this->findById($id);
        if (!$media) return false;

        // Supprimer les fichiers
        $filePath = __DIR__ . '/../../public' . $media['url'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        if ($media['thumbnail_url']) {
            $thumbPath = __DIR__ . '/../../public' . $media['thumbnail_url'];
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }

        // Supprimer WebP
        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $filePath);
        if (file_exists($webpPath)) {
            unlink($webpPath);
        }

        // Supprimer de la BDD
        $stmt = $this->db->prepare('DELETE FROM media_library WHERE id = ?');
        return $stmt->execute([$id]);
    }

    private function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite PHP)',
            UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
            UPLOAD_ERR_PARTIAL => 'Upload partiel',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Erreur écriture disque'
        ];
        return $messages[$error] ?? 'Erreur inconnue';
    }

    public function count(?string $folder = null): int
    {
        if ($folder) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM media_library WHERE folder = ?');
            $stmt->execute([$folder]);
        } else {
            $stmt = $this->db->query('SELECT COUNT(*) FROM media_library');
        }
        return (int) $stmt->fetchColumn();
    }
}
