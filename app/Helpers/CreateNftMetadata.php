<?php

namespace App\Helpers;

use RuntimeException;

class CreateNftMetadata
{
    private $additional_info;

    private array $tokenMetaIcon;
    private array $tokenDescription;
    private string $tokenMetaIconMediaType;

    public function __construct()
    {
        $this->tokenMetaIcon = [];
        $this->tokenDescription = [];
        $this->tokenMetaIconMediaType = '';
    }

    public function generateNftMetadata($policy_id, array $validated, string $additional_info): ?string
    {
        $nftData = [];

        $this->additional_info = $additional_info;

        $nftData['name'] = !empty($validated['name']) ? $validated['name'] : null;
        $nftData['link'] = !empty($validated['link']) ? $validated['link'] : null;
        $nftData['number'] = !empty($validated['number']) ? $validated['number'] : 0;
        $nftData['ticker'] = !empty($validated['ticker']) ? $validated['ticker'] : null;
        $nftData['category'] = !empty($validated['category']) ? $validated['category'] : null;
        $nftData['type'] = !empty($validated['type']) ? $validated['type'] : null;
        $nftData['decimals'] = !empty($validated['decimals']) ? $validated['decimals'] : 0;

        $nftData['image'] = null;
        $nftData['description'] = null;
            
        if (!empty($this->additional_info)) {
            $tokenMetaImage = $this->extractLinkTag($this->additional_info);

            if (!empty($tokenMetaImage)) {
                $tokenMetaImage = $this->cleanAndLimitString($this->extractHref($tokenMetaImage));
            } else {
                $tokenMetaImage = '';
            }
        
            $this->additional_info =$this->removeLinkTags($this->additional_info);

            $additional_info_plain_text= $this->stripHTMLTagsWhiteSpaces($this->additional_info);
                    
            $this->tokenDescription = $this->splitUtf8String($additional_info_plain_text, 64);
            
            $nftData['description'] = !empty($this->tokenDescription) ? $this->tokenDescription : null;

            $imgTag = $this->extractImgTag($this->additional_info);
          
            if (!empty($imgTag)) {           
                $imgTag = $this->extractSrc($imgTag);

                if (!empty($imgTag)) {                    
                    $this->tokenMetaIconMediaType = 'image/jpeg';

                    if (($imgTag = $this->compressBase64Image(true, $imgTag, 3072, $this->tokenMetaIconMediaType)) !== null) {
                        $this->tokenMetaIcon = $this->splitIntoChunks('data:'.$this->tokenMetaIconMediaType.';base64,'.$imgTag, 64);

                        $nftData['image']     = !empty($this->tokenMetaIcon) ? $this->tokenMetaIcon : null;
                        $nftData['mediaType'] = $this->tokenMetaIconMediaType;
                    }
                }
            }
        }

        $metadataVersion = '1.0';

        $nftMetaData  = '{'.PHP_EOL;

        if ($nftData['number'] == 1) {
            $nftMetaData .= '  "721": {'.PHP_EOL;
        } else {
            $nftMetaData .= '  "20": {'.PHP_EOL;
        }

        $nftMetaData .= '    "'.$policy_id.'": {'.PHP_EOL;
        $nftMetaData .= '      "'.$nftData['name'].'": {'.PHP_EOL;

        $nftMetaData .= '        "name": "'.$nftData['name'].'",'.PHP_EOL;
        $nftMetaData .= '        "ticker": "'.$nftData['ticker'].'",'.PHP_EOL;

        if (!empty($nftData['category'])) {
            $nftMetaData .= '        "category": "'.$nftData['category'].'",'.PHP_EOL;
        }

        if (!empty($nftData['type'])) {
            $nftMetaData .= '        "type": "'.$nftData['type'].'",'.PHP_EOL;
        }

        if (empty($nftData['image']) && empty($nftData['description'])) {
            $nftMetaData .= '        "decimals": "'.$nftData['decimals'].'"'.PHP_EOL;
        } else {
            $nftMetaData .= '        "decimals": "'.$nftData['decimals'].'",'.PHP_EOL;
        }

        if (!empty($nftData['image'])) {
            $nftMetaData .= '        "image": ['.PHP_EOL;;

            $cnt = 0;

            foreach($nftData['image'] as $image) {            
                if (strlen($image)) {
                    if ($cnt == (count($nftData['image']) - 1)) {
                        $nftMetaData .= '          "'.$image.'"'.PHP_EOL;
                    } else {
                        $nftMetaData .= '          "'.$image.'",'.PHP_EOL;
                    }

                    $cnt = $cnt + 1;
                }
            }

            $nftMetaData .= '        ],'.PHP_EOL;

            if (empty($nftData['description'])) {
                $nftMetaData .= '        "mediaType": "'.$nftData['mediaType'].'"'.PHP_EOL;
            } else {
                $nftMetaData .= '        "mediaType": "'.$nftData['mediaType'].'",'.PHP_EOL;
            }
        }

        if (!empty($nftData['description'])) {
            $nftMetaData .= '        "description": ['.PHP_EOL;;

            $cnt = 0;

            foreach($nftData['description'] as $image) {            
                if (strlen($image)) {
                    if ($cnt == (count($nftData['description']) - 1)) {
                        $nftMetaData .= '          "'.$image.'"'.PHP_EOL;
                    } else {
                        $nftMetaData .= '          "'.$image.'",'.PHP_EOL;
                    }

                    $cnt = $cnt + 1;
                }
            }

            $nftMetaData .= '        ]'.PHP_EOL;
        }

        $nftMetaData .= '      }'.PHP_EOL;
        $nftMetaData .= '    },'.PHP_EOL;
        $nftMetaData .= '    "version": "'.$metadataVersion.'"'.PHP_EOL;
        $nftMetaData .= '  }'.PHP_EOL;
        $nftMetaData .= '}'.PHP_EOL;
        
        return $nftMetaData;
    }

    public function generateTransactionMetadata(string $additional_info): ?string
    {
        $metaData = [];

        $this->additional_info = $additional_info;

        $metaData['image'] = null;
        $metaData['description'] = null;
        
        if (!empty($this->additional_info)) {
            $tokenMetaImage = $this->extractLinkTag($this->additional_info);

            if (!empty($tokenMetaImage)) {
                $tokenMetaImage = $this->cleanAndLimitString($this->extractHref($tokenMetaImage));
            } else {
                $tokenMetaImage = '';
            }
        
            $this->additional_info =$this->removeLinkTags($this->additional_info);

            $additional_info_plain_text= $this->stripHTMLTagsWhiteSpaces($this->additional_info);
                    
            $this->tokenDescription = $this->splitUtf8String($additional_info_plain_text, 64);
            
            $metaData['description'] = !empty($this->tokenDescription) ? $this->tokenDescription : null;

            $imgTag = $this->extractImgTag($this->additional_info);
                   
            if (!empty($imgTag)) {           
                $imgTag = $this->extractSrc($imgTag);

                if (!empty($imgTag)) {
                    $this->tokenMetaIconMediaType = 'image/jpeg';

                    if (($imgTag = $this->compressBase64Image(true, $imgTag, 3072, $this->tokenMetaIconMediaType)) !== null) {
                        $this->tokenMetaIcon = $this->splitIntoChunks('data:'.$this->tokenMetaIconMediaType.';base64,'.$imgTag, 64);

                        $metaData['image']     = !empty($this->tokenMetaIcon) ? $this->tokenMetaIcon : null;
                        $metaData['mediaType'] = $this->tokenMetaIconMediaType;
                    }
                }
            }
        }

        $policy_id = 'private';

        $metaData['name'] = 'wnt';

        $metadataVersion = '1.0';

        $nftMetaData  = '{'.PHP_EOL;

        $nftMetaData .= '  "20813": {'.PHP_EOL;        

        $nftMetaData .= '    "'.$policy_id.'": {'.PHP_EOL;
        $nftMetaData .= '      "'.$metaData['name'].'": {'.PHP_EOL;
        
        if (!empty($metaData['image'])) {
            $nftMetaData .= '        "image": ['.PHP_EOL;;

            $cnt = 0;

            foreach($metaData['image'] as $image) {            
                if (strlen($image)) {
                    if ($cnt == (count($metaData['image']) - 1)) {
                        $nftMetaData .= '          "'.$image.'"'.PHP_EOL;
                    } else {
                        $nftMetaData .= '          "'.$image.'",'.PHP_EOL;
                    }

                    $cnt = $cnt + 1;
                }
            }

            $nftMetaData .= '        ],'.PHP_EOL;

            if (empty($metaData['description'])) {
                $nftMetaData .= '        "mediaType": "'.$metaData['mediaType'].'"'.PHP_EOL;
            } else {
                $nftMetaData .= '        "mediaType": "'.$metaData['mediaType'].'",'.PHP_EOL;
            }
        }

        if (!empty($metaData['description'])) {
            $nftMetaData .= '        "description": ['.PHP_EOL;;

            $cnt = 0;

            foreach($metaData['description'] as $image) {            
                if (strlen($image)) {
                    if ($cnt == (count($metaData['description']) - 1)) {
                        $nftMetaData .= '          "'.$image.'"'.PHP_EOL;
                    } else {
                        $nftMetaData .= '          "'.$image.'",'.PHP_EOL;
                    }

                    $cnt = $cnt + 1;
                }
            }

            $nftMetaData .= '        ]'.PHP_EOL;
        }

        $nftMetaData .= '      }'.PHP_EOL;
        $nftMetaData .= '    },'.PHP_EOL;
        $nftMetaData .= '    "version": "'.$metadataVersion.'"'.PHP_EOL;
        $nftMetaData .= '  }'.PHP_EOL;
        $nftMetaData .= '}'.PHP_EOL;
        
        if (empty($metaData['description'])) {
            return '';
        }

        return $nftMetaData;
    }

    private function extractSrc(string $htmlString): ?string
    {
        if (str_starts_with($htmlString, "src=")) {
            return substr($htmlString, 5);
        }
        
        return null;
    }

    private function extractImgTag(string $htmlString): ?string 
    {
        $imgTagRegex = '/<img[^>]*>/i';

        if (preg_match($imgTagRegex, $htmlString, $match)) {
            $result = $match[0];

            if (strlen($result) > 6) {
                $result = substr($result, 5, -2); 
            }

            return $result;
        }

        return null;
    }

    private function splitUtf8String(string $input, int $chunkSize): array 
    {
        $utf8Bytes = mb_convert_encoding($input, 'UTF-8', 'UTF-8');

        $result = [];
        
        $start = 0;
        
        $inputLength = strlen($utf8Bytes);

        while ($start < $inputLength) {
            $end = $start + $chunkSize;
        
            if ($end >= $inputLength) {
                $end = $inputLength;
            } else {
            
                while ($end > $start && (ord($utf8Bytes[$end]) & 0b11000000) === 0b10000000) {
                    $end--;
                }
                if ($end === $start) {
                    $end = $start + $chunkSize;
                }
            }

            $chunk = substr($utf8Bytes, $start, $end - $start);

            $result[] = mb_convert_encoding($chunk, 'UTF-8', 'UTF-8');

            $start = $end;
        }

        return $result;
    }

    private function stripHTMLTagsWhiteSpaces(string $str): string 
    {    
        $str = preg_replace('/<[^>]*>/', '', $str);

        $str = preg_replace('/\s{2,}|\r?\n|\r/', ' ', $str);

        return trim($str);
    }

    private function extractLinkTag(string $htmlString): ?string
    {
        $linkTagRegex = '/<a[^>]*>/i';

        if (preg_match($linkTagRegex, $htmlString, $match)) {
            $result = $match[0];

            if (strlen($result) > 6) {
                $result = substr($result, 6, -2);
            }

            return $result;
        }

        return null;
    }

    private function extractHref(string $htmlString): ?string 
    {
        $hrefTagRegex = '/href="([^"]*)"/i';

        if (preg_match($hrefTagRegex, $htmlString, $match)) {
            return $match[1];
        }

        return null;
    }

    private function cleanAndLimitString(string $input): string 
    {
        $cleanedString = preg_replace('/\s+/', '', $input);
    
        return substr($cleanedString, 0, 64);
    }

    private function removeLinkTags(string $htmlString): string 
    {
        return preg_replace('/<\/?a[^>]*>/i', '', $htmlString);
    }

    private function splitIntoChunks(string $string, int $chunkSize): array 
    {
        $chunks = [];

        $length = mb_strlen($string, 'UTF-8');

        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunks[] = mb_substr($string, $i, $chunkSize, 'UTF-8');
        }

        return $chunks;
    }

    private function compressBase64Image(bool $scaleImage, string $base64Image, int $maxSizeInBytes, string $mimeType = 'image/jpeg'): ?string
    {
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
    
        if ($imageData === false) {
            return null;
        }

        switch ($mimeType) {
            case 'image/jpeg':
                $img = imagecreatefromstring($imageData);
                break;
            case 'image/png':
                $img = imagecreatefromstring($imageData);
                break;
            case 'image/webp':
                $img = imagecreatefromstring($imageData);
                break;
            default:
                return null;
        }

        if (!$img) {
            return null;
        }

        $width = imagesx($img);
        $height = imagesy($img);

        if ($scaleImage) {
            $scaleFactor = sqrt($maxSizeInBytes / strlen($imageData));
            $newWidth = max(1, (int)($width * $scaleFactor));
            $newHeight = max(1, (int)($height * $scaleFactor));
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
    
        if ($mimeType === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $quality = 90;
    
        do {
            ob_start();

            if ($mimeType === 'image/jpeg') {
                imagejpeg($canvas, null, $quality);
            } elseif ($mimeType === 'image/png') {
                $pngQuality = (int)((100 - $quality) / 10);
                imagepng($canvas, null, $pngQuality);
            }
        
            $compressedData = ob_get_clean();
            $sizeInBytes = strlen($compressedData);
            $quality -= 5;
        
        } while ($sizeInBytes > $maxSizeInBytes && $quality > 10);

        imagedestroy($img);
        imagedestroy($canvas);

        return base64_encode($compressedData);
    }

}