<?php

	namespace Drupal\payment_asp\Plugin\Block;

	/**
	* Custom made block for links
	* @Block(
	*   id = "PaymentASP Checkout Block",
	*   admin_label = @Translation("PaymentASP Checkout Block"),
	*   category = @Translation("Block to show payment methods"),
	* )
	**/

	use Drupal\Core\Block\BlockBase;

	class PaymentASPBlock extends BlockBase {
		/**
		* {@inheritdoc}
		*/
		public function build() {
			$block['form'] = \Drupal::formBuilder()->getForm('Drupal\payment_asp\Form\PaymentASPForm');
			$block['block'] = [
				'#type' => 'markup',
				'#markup' => '<div class="payment_method_choosen"></div>',
			];
			
			return $block;
		}

	  // /**
	  //  * {@inheritdoc}
	  //  */
	  // protected function blockAccess(AccountInterface $account) {
	  // 	return AccessResult::allowedIfHasPermission($account, 'access content');
	  // }
	}