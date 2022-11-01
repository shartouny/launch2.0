<?php

namespace SunriseIntegration\Shopify\Models;


/**
 * Class Customer
 *
 * @method getAcceptsMarketing()
 * @method getAddresses()
 * @method getCreatedAt()
 * @method getDefaultAddress()
 * @method getEmail()
 * @method getPhone()
 * @method getFirstName()
 * @method getId()
 * @method getMultipassIdentifier()
 * @method getLastName()
 * @method getLastOrderId()
 * @method getLastOrderName()
 * @method getNote()
 * @method getOrdersCount()
 * @method getSendEmailWelcome()
 * @method getPassword()
 * @method getPasswordConfirmation()
 * @method getSendEmailInvite=False()
 * @method getState()
 * @method getTags()
 * @method getTaxExempt()
 * @method getTotalSpent()
 * @method getUpdatedAt()
 * @method getVerifiedEmail()
 *
 *
 * @method setAcceptsMarketing($marketing)
 * @method setAddresses($address)
 * @method setCreatedAt($createdAt)
 * @method setDefaultAddress($address)
 * @method setEmail($email)
 * @method setPhone($phone)
 * @method setFirstName($name)
 * @method setId($id)
 * @method setMultipassIdentifier($identifier)
 * @method setLastName($lastName)
 * @method setLastOrderId($id)
 * @method setLastOrderName($name)
 * @method setNote($note)
 * @method setOrdersCount($count)
 * @method setSendEmailWelcome($welcome)
 * @method setPassword($password)
 * @method setPasswordConfirmation($confirmation)
 * @method setSendEmailInvite($flag)
 * @method setState($state)
 * @method setTags($tags)
 * @method setTaxExempt($tax)
 * @method setTotalSpent($total)
 * @method setUpdatedAt($date)
 * @method setVerifiedEmail($email)
 *
 *
 * @package SunriseIntegration\Shopify\Models
 */
class Customer extends AbstractEntity {


	#region Properties
	protected $accepts_marketing;
	/**
	 * @var array( \Core\Shopify\CustomerAddress )
	 */
	protected $addresses = [];
	/**
	 * @var string
	 */
	protected $created_at;
	/**
	 * @var array ( \Core\Shopify\Address )
	 */
	protected $default_address = [];
	protected $email;
	protected $phone;
	protected $first_name;
	protected $id;

	protected $multipass_identifier;
	protected $last_name;
	protected $last_order_id;
	protected $last_order_name;
	protected $note;
	protected $orders_count;
	protected $send_email_welcome = false;
	protected $password;
	protected $password_confirmation;
	protected $send_email_invite = false;

	/**
	 * disabled: customers are disabled by default until they are invited. Staff accounts can disable a customer's account at any time.
	invited: the customer has been emailed an invite to create an account that saves their customer settings.
	enabled: the customer accepted the email invite and created an account.
	declined: the customer declined the email invite to create an account
	 * @var string
	 */
	protected $state;
	protected $tags;
	protected $tax_exempt;
	protected $total_spent;
	protected $updated_at;
	protected $verified_email;
	#endregion
}
