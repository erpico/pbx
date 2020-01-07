<?php

class ErpicoFileUploader {

    /**
     * move tmp file to a specified upload dir
     * @param $file string A single $_FILES object
     * @param $uploadPath int|null Upload dir
     * @return bool True if a successfull file move
     */
    public static function moveFile($file, $uploadPath = null) {
        if ($file['error'] == UPLOAD_ERR_OK) {
            $name = $file["name"];
            $fileInfo = pathinfo($name);

            switch (strtolower($fileInfo['extension'])) {
                case 'php':
                case 'exe':
                case 'cgi':
                case 'js':
                case 'html':
                  return [ 'status' => "error"];
                default:
                  break;
            }

            $randomFileName = static::getRandomFileName($name);
            $fullFileName = $randomFileName . "." . $fileInfo['extension'];

            if (!move_uploaded_file($file["tmp_name"], "$uploadPath/$fullFileName")) {
                return [ 'status' => "error"];
            }

            $data = [
                'name' => $name,
                'time' => Date('y-m-d h:i:s'),
            ];

            file_put_contents($uploadPath . '/' . $randomFileName . '.info.txt', json_encode($data));

            return ['filename' => $name, 'hash' => $fullFileName ];
        } else {
            return [ 'status' => "error"];
        }
    }

    /**
     * Assigns a random and unique filename to a file being moved
     * @param $sourceFile string
     * @return string
     * */
    public static function getRandomFileName($sourceFile) {
        return Date("ymdhis") . rand();
    }

    public static function getFileInfoByHash($hash, $uploadPath) {
        if (file_exists($filename = $uploadPath . '/' . $hash . '.info.txt')) {
            return json_decode(file_get_contents($filename), 1);
        }
    }


}
