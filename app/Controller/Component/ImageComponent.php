<?php

App::uses('Component', 'Controller');

App::import('Component', 'CakeS3.CakeS3');

class ImageComponent extends Component {
    
    /**
     * S3 Component property
     *
     * @access public
     */
    var $cakes3c;

    public function __construct() {
        
        // initialize s3 component..
        $this->cakes3c = & new CakeS3Component(new ComponentCollection(), array(
            's3Key' => AMAZON_S3_KEY,
            's3Secret' => AMAZON_S3_SECRET_KEY,
            'bucket' => BUCKET_NAME,
            'endpoint' => END_POINT
        ));
        
    }


    /**
     * watermarkingOnImage method
     * This function is used to make image with adding border and appending logo of clickin on a image.
     * 
     * @param string $destImagePath
     * @return string
     */
    public function watermarkingOnImage($destImagePath)
    {
        if($destImagePath == '')
            return '';
        //$destImagePath = 'https://s3.amazonaws.com:443/qbprod/0861a187739f4c698075c4da8e8370bf00';
        //$destImagePath = WWW_ROOT . "images/3wm.png";
        $image = imagecreatefromjpeg($destImagePath);
        list($width, $height, $filetype) = getimagesize($destImagePath);        
        
        $srcImagePath = WWW_ROOT . 'images/clickin.png';
        $src = imagecreatefrompng( $srcImagePath );
        list($width1, $height1) = getimagesize($srcImagePath);
        
        $newWidth = $width1;
        $newHeight = $height1;
        
        imagecopy( $image, $src, (($width/2)-($width1/2)), 10, 0, 0, $newWidth, $newHeight);
        
        if ($filetype === NULL) {
            return false;
        }
        $convertedImgUrl = '';
        switch ($filetype) {
            case IMAGETYPE_GIF:
                $convertedImgUrl = $this->addBorderToImage($image, 'gif');
                break;
            case IMAGETYPE_JPEG:
                $convertedImgUrl = $this->addBorderToImage($image, 'jpeg');
                break;
            case IMAGETYPE_PNG:
                $convertedImgUrl = $this->addBorderToImage($image, 'png');
                break;
            default:
                return false;
        }
        //return HOST_ROOT_PATH . 'images/' . $convertedImgUrl;
        return $convertedImgUrl;
        //echo '<img src="'.HOST_ROOT_PATH . 'images/' . $convertedImgUrl.'">';
        //exit;
    }
    
    /**
     * addBorderToImage method.
     * This function is used to set border on provide image object.
     * 
     * @param object $im
     * @param string $mime_type
     * @return string image path
     */
    public function addBorderToImage($im, $mime_type) {
        
        $border = 20;
        
        $width = imagesx($im);
        $height = imagesy($im);
        
        $img_adj_width = $width + (2 * $border);
        $img_adj_height = $height + (2 * $border);
        
        $newimage = imagecreatetruecolor($img_adj_width, $img_adj_height);
        
        // creating object of border color..
        $border_color = imagecolorallocate($newimage, 246, 190, 190);
        imagefilledrectangle($newimage, 0, 0, $img_adj_width, $img_adj_height, $border_color);
        
        imagecopyresized($newimage, $im, $border, $border, 0, 0, $width, $height, $width, $height);
        
        // generating random image name for tomporary file path.
        $randomImageName = strtotime(date('Y-m-d H:i:s'));
        switch ($mime_type) {
            case 'gif':
                $fileName = $randomImageName.'.gif';
                $srcImagePath = WWW_ROOT . 'images/'.$fileName;
                imagegif($newimage, $srcImagePath);
            case 'jpeg':
                $fileName = $randomImageName.'.jpeg';
                $srcImagePath = WWW_ROOT . 'images/'.$fileName;
                imagejpeg($newimage, $srcImagePath);
                break;
            case 'png':
                $fileName = $randomImageName.'.png';
                $srcImagePath = WWW_ROOT . 'images/'.$fileName;
                imagepng($newimage, $srcImagePath);
                break;
            default:
                break;
        }
        chmod("$srcImagePath", 0777);
        
        // upload file on s3 and delete from local folder..
        $response = $this->cakes3c->putObject($srcImagePath, $fileName, $this->cakes3c->permission('public_read_write'));
        if($response['url']!='')
            unlink($srcImagePath);        
        return $response['url'];
        // return $fileName;
    }   
    
}