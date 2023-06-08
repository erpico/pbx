<?php


namespace App\helpers;


class ErpicoFileUploader {

    /**
     * move tmp file to a specified upload dir
     * @param $file string A single $_FILES object
     * @param $userId int User id who loaded a file
     * @param $uploadPath int|null Upload dir
     * @return bool True if a successfull file move
     */
    public static function moveFile($file, $userId) {
        $config = require(__DIR__ . '/../settings.php');

        $uploadPaths = $config['settings']['uploadPath'];

        if ($file['error'] == UPLOAD_ERR_OK) {
            $name = $file["name"];
            $fileInfo = pathinfo($name);

            if (isset($file['type'])) {
                $type = explode('/', $file['type'])['0'];

                switch($type) {
                    case 'image':
                        $uploadPath = $uploadPaths['photos'];
                        break;
                    case 'audio':
                        $uploadPath = $uploadPaths['audio'];
                        break;
                    default:
                        return [ 'status' => "error"];
                        break;
                }
            }

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

            $randomFileName = static::getRandomFileName($name, $userId);
            $fullFileName = $randomFileName . "." . $fileInfo['extension'];

            if (!move_uploaded_file($file["tmp_name"], "$uploadPath/$fullFileName")) {
                return ['status' => $file["error"]];
            }

            $data = [
                'name' => $name,
                'time' => (new \DateTime())->format('Y-m-d H:i:s'),
                'user' => $userId,
                'extension' => strtolower($fileInfo['extension'])
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
     * @param $userId int
     * @return string
     * */
    public static function getRandomFileName($sourceFile, $userId) {
        return Date("ymdhis") . rand();//$userId;
    }

    public static function getFileInfoByHash($hash, $uploadPath) {
        if (file_exists($filename = $uploadPath . '/' . $hash . '.info.txt')) {
            return json_decode(file_get_contents($filename), 1);
        }
    }


}
