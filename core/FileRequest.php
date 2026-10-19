<?php

class FileRequest extends FileSystem
{
    protected mixed $files;
    protected array $items;

    public function __construct(?string $field = null)
    {
        $this->withImageOptimized = false;

        if (!$field || !isset($_FILES[$field])) {
            return;
        }

        $files = $_FILES[$field];
        $this->files = $files;

        // Handle multiple upload
        if (is_array($files['name'])) {
            $this->isMultiple = true;
            $this->items = [];

            foreach ($files['name'] as $i => $name) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $this->items[] = [
                    'name'      => $name,
                    'size'      => $files['size'][$i],
                    'extension' => $this->getExtension($name),
                    'tmp_name'  => $files['tmp_name'][$i],
                    'type'      => $files['type'][$i],
                    'error'     => $files['error'][$i],
                ];
            }

        } else {
            // Single upload
            if ($files['error'] === UPLOAD_ERR_OK) {
                $this->isMultiple  = false;
                $this->name      = $files['name'];
                $this->size      = $files['size'];
                $this->extension = $this->getExtension($files['name']);
                $this->tmp_name  = $files['tmp_name'];
                $this->type      = $files['type'];
                $this->error     = $files['error'];
            }
        }
        
    }

    protected function getExtension(string $filename) 
    {
		return pathinfo($filename, PATHINFO_EXTENSION);
	}
}