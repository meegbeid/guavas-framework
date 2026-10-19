<?php

//use FileRequest;

class File extends FileSystem
{

    private static function normalizePath(string $path): string
    {
        $path = str_replace(ROOT_DIR, '', $path);
        return rtrim(ROOT_DIR, '/') . '/' . ltrim($path, '/');
    }

    public function __construct(?array $files = null)
    {
        if ($files) {
            $this->name = $files['name'] ?? null;
            $this->type = $files['type'] ?? null;
            $this->tmp_name = $files['tmp_name'] ?? null;
            $this->error = $files['error'] ?? UPLOAD_ERR_NO_FILE;
            $this->size = isset($files['size']) ? (int)$files['size'] : null;

            if ($this->type) {
                $this->extension = $this->getMimeExtension($this->type);
            }
        }
    }

    public static function exists(string $pathFile): bool
    {
        return is_readable(self::normalizePath($pathFile));
    }

    public static function read(string $pathFile): array|false
    {
        $pathFile = self::normalizePath($pathFile);

        if (!is_readable($pathFile)) {
            return false;
        }
        return file($pathFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public static function write(string $pathFile, string|array $content = '', bool $replaceOrNew = true)
    {
        $pathFile = self::normalizePath($pathFile);

        if (is_array($content)) {
            $content = implode(PHP_EOL, $content);
        }

        $dir = dirname($pathFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!file_exists($pathFile) && !$replaceOrNew) {
            return false;
        }

        $result = file_put_contents($pathFile, $content);
        return $result !== false;
    }

    public static function request(string $key): File|false
    {
        if (!isset($_FILES[$key])) {
            return false;
        }

        return new self($_FILES[$key]);
    }

    public static function loadFromString(string $strFile)
	{
        if (empty($strFile) || !str_starts_with($strFile, 'data:')) {
            return false;
        }
        [$meta, $data] = explode(',', $strFile, 2);

        if (!str_contains($meta, ';base64')) {
            return false;
        }
        $mime = str_replace(['data:', ';base64'], '', $meta);
        $binary = base64_decode($data, true);

        if ($binary === false) {
            return false;
        }

        $self = new self();
        $self->type = $mime;
        $self->extension = $self->getMimeExtension($mime);
        $self->error = UPLOAD_ERR_OK;
        $self->usingTmp = true;

        $pathTmp = storage('/tmp', true);

        if (!is_dir($pathTmp)) {
            mkdir($pathTmp, 0777, true);
        }

        $fileName = \Hash::uuid() . '.' . ($self->extension ?? 'bin');
        $fullPath = rtrim($pathTmp, '/') . '/' . $fileName;

        if (file_put_contents($fullPath, $binary) === false) {
            return false;
        }

        $self->tmp_name = $fullPath;
        $self->name = $fileName;
        $self->size = filesize($fullPath);

        return $self;
	}
}