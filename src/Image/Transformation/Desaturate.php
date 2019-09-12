<?php
namespace Imbo\Image\Transformation;

use Imbo\Exception\TransformationException,
    ImagickException;

/**
 * Desaturate transformation
 *
 * @package Image\Transformations
 */
class Desaturate extends Transformation {
    /**
     * {@inheritdoc}
     */
    public function transform(array $params) {
        try {
            $this->imagick->modulateImage(100, 0, 100);
        } catch (ImagickException $e) {
            throw new TransformationException($e->getMessage(), 400, $e);
        }

        $this->image->hasBeenTransformed(true);
    }
}
