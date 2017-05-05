<?php

namespace Brisum\Wordpress\ImageEditor;

use WP_Error;
use WP_Image_Editor_GD;

class Gd extends WP_Image_Editor_GD {
	/**
	 *
	 * @param int $max_w
	 * @param int $max_h
	 * @param bool|array $crop
	 * @return resource|WP_Error
	 */
	protected function _resize( $max_w, $max_h, $crop = false, $bg = null ) {
		$dims = $this->imageResizeDimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );
		if ( ! $dims ) {
			return new WP_Error( 'error_getting_dimensions', __('Could not calculate resized image dimensions'), $this->file );
		}
		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		$resized = wp_imagecreatetruecolor( $dst_w, $dst_h );

		// custom
		$bg = $bg ? $bg : array(255, 255, 255);
		$transparent = imagecolorallocatealpha($resized, $bg[0], $bg[1], $bg[2], 127);
		imagefilledrectangle($resized, 0, 0, $dst_w, $dst_h, $transparent);

		imagecopyresampled(
			$resized,
			$this->image,
			$dst_x,
			$dst_y,
			$src_x,
			$src_y,
			$dst_w - ($dst_x * 2),
			$dst_h - ($dst_y * 2),
			$src_w,
			$src_h
		);

		// convert from full colors to index colors, like original PNG.
		if ('image/png' == $this->mime_type && !imageistruecolor($this->image)) {
			imagetruecolortopalette($resized, false, imagecolorstotal($this->image));
		}

		if ( is_resource( $resized ) ) {
			$this->update_size( $dst_w, $dst_h );
			return $resized;
		}

		return new WP_Error( 'image_resize_error', __('Image resize failed.'), $this->file );
	}

	/**
	 * Based in the image_resize_dimensions of wordpress
	 */
	public function imageResizeDimensions($orig_w, $orig_h, $dest_w, $dest_h, $crop = false, $far = false, $iar = false)
	{
		if ($orig_w <= 0 || $orig_h <= 0) {
			return false;
		}
		// at least one of dest_w or dest_h must be specific
		if ($dest_w <= 0 && $dest_h <= 0) {
			return false;
		}

		$dest_x = 0;
		$dest_y = 0;
		$isFixSize = $dest_w && $dest_h;
		if ($crop) {
			// crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
			$aspect_ratio = $orig_w / $orig_h;
			$new_w = $isFixSize ? $dest_w : min($dest_w, $orig_w);
			$new_h = $isFixSize ? $dest_h : min($dest_h, $orig_h);

			if (!$new_w) {
				$new_w = intval($new_h * $aspect_ratio);
			}

			if (!$new_h) {
				$new_h = intval($new_w / $aspect_ratio);
			}

			$size_ratio = min($new_w / $orig_w, $new_h / $orig_h);

			$crop_w = round($new_w / $size_ratio);
			$crop_h = round($new_h / $size_ratio);

			// $s_x = floor( ($orig_w - $crop_w) / 2 );
			// $s_y = floor( ($orig_h - $crop_h) / 2 );

			// fix resize
			$s_x = 0;
			$s_y = 0;

			$dest_x = ($crop_w > $orig_w) ? floor(($orig_w - $crop_w) / 2 * $size_ratio * -1) : 0;
			$dest_y = ($crop_h > $orig_h) ? floor(($orig_h - $crop_h) / 2 * $size_ratio * -1) : 0;

			$size_ratio_w = $new_w / $orig_w;
			$size_ratio_h = $new_h / $orig_h;
			if ($size_ratio_w > $size_ratio_h) {
				$crop_w = min($crop_w, $orig_w);
			}
			if ($size_ratio_w < $size_ratio_h) {
				$crop_h = min($crop_h, $orig_h);
			}
			// end fix resize
		} else {
			// don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
			$crop_w = $orig_w;
			$crop_h = $orig_h;

			$s_x = 0;
			$s_y = 0;

			list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $dest_w, $dest_h );
		}

		if ($far) {
			switch ($far) {
				case 'L':
				case 'TL':
				case 'BL':
					$s_x = 0;
					$s_y = round(($dest_h - $orig_h) / 2);
					break;
				case 'R':
				case 'TR':
				case 'BR':
					$s_x = round($dest_w - $orig_w);
					$s_y = round(($dest_h - $orig_h) / 2);
					break;
				case 'T':
				case 'TL':
				case 'TR':
					$s_x = round(($dest_w - $orig_w) / 2);
					$s_y = 0;
					break;
				case 'B':
				case 'BL':
				case 'BR':
					$s_x = round(($dest_w - $orig_w) / 2);
					$s_y = round($dest_h - $orig_h);
					break;
				case 'C':
				default:
					$s_x = round(($dest_w - $orig_w) / 2);
					$s_y = round(($dest_h - $orig_h) / 2);

			}

		}
		if ($iar) {
			//ignore aspect radio and resize the image
			$crop_w = $orig_w;
			$crop_h = $orig_h;

			$s_x = 0;
			$s_y = 0;

			$new_w = ceil($orig_w * $dest_w / $orig_w);
			$new_h = ceil($orig_h * $dest_h / $orig_h);

		}

		// if the resulting image would be the same size or larger we don't want to resize it
		if ( $new_w >= $orig_w && $new_h >= $orig_h && $dest_w != $orig_w && $dest_h != $orig_h ) {
			return false;
		}

		// the return array matches the parameters to imagecopyresampled()
		// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
		return array($dest_x, $dest_y, (int)$s_x, (int)$s_y, (int)$new_w, (int)$new_h, (int)$crop_w, (int)$crop_h);
	}
}