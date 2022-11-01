# teelaunch-2.0

## Required github Repo Access
* teelaunch2admin
* teelaunch-2
* shopify-api
* etsy-api
* orderdesk-api

## Environment Requirements
* php ^7.3
* PHP extension php-oauth (see wiki for instructions) 
* PHP extension imagick with PDF write enabled (see wiki for instructions) 
* redis
* mysql

## Required API Keys
* AWS S3
* Mandrill / transactional email
* Braintree
* Stripe

## Installing
* Setup https://github.com/Sunrise-Integration/teelaunch2adminv2 first, as this contains the database 
* Make sure all required PHP Extensions are installed
* Copy .env.example to a file called .env
* Edit variables inside .env to match your environment
* Run `composer install` to install PHP dependencies
* Generate an app key by running `php artisan key:generate`
* Run `npm install` to install javascript dependencies
* Run `npm run dev` to build or `npm run watch` to watch for changes

## Server Setup  
* Make sure all required PHP Extensions are installed
* Setup Supervisor to keep Horizon running in order to process Jobs (i.e. product generation)

## Understanding Platform Data Syncs
Platform Data Syncs are setup as a series of Commands that allow for syncing all Platforms, PlatformStores for a specific Platform or just targeting a single specific PlatformStore. The general flow for syncing all Etsy stores would look like:

ImportEtsyProducts > ImportEtsyStoreProducts > EtsyManager->importProducts() > EtsyManager->processProduct()

*Note: This process can be initiated either by calling ImportPlatformProducts which would also sync all other Platforms, or just ImportEtsyProducts which will only sync Etsy products.*

* The Command ImportPlatformProducts kicks off the imports for all Platforms which will call each Platforms Import (ImportEtsyProducts, ImportShopifyProducts). 
* This Platform Import will then call Imports for each specific PlatformStore (ImportEtsyStoreProducts, ImportShopifyStoreProducts)
* The PlatformStore Import calls the PlatformManager (EtsyManager, ShopifyManager) method importProducts. 
* The importProducts method is where the API is used to retrieve data from the PlatformStore and then processed using the PlatformManager's processProduct method. 
* The processProduct method should transform the data using a PlatformFormatter (EtsyProductFormatter, ShopifyProductFormatter) and then update or create rows in the PlatformProducts and PlatformProductVariants tables.

## Queue Listener
teelaunch uses Laravel Queues and Jobs to run a majority of its processes. This can be run with the following command: `php artisan queue:listen --timeout=0`

## Horizon
Laravel Horizon is a user friendly interface for monitoring the apps jobs and queues. You may access it by navigating to `/horizon`

## Image Processing
When a Product is created, or it's Art File is updated the ProcessProductArtFile, ProcessProductPrintFile and ProcessProductMockupFile jobs are called. These processes run the required adjustments needed to generate and store their associated files. Art and Print Files are processed on the app server. Mockup files are processed on a separate server running Photoshop. Once a request is sent to the Mockup processing server, the app will query for the existence of the Mockup file over a period of 10 minutes. If no file is found within that period, then the process will be attempted 2 more times. After 3 failures to generate a Mockup file the process will quit.

## Logs
Global process logs can be found in `storage/logs`. Account specific processes such as importing orders from a PlatformStore or processing Images are stored under `storage/logs/accounts/ACCOUNT_ID` to allow for better tracing.

# Integrations

## AWS S3
AWS S3 is used to store files. Each environment (production, stage, local) has their own bucket. This could result in other local environments overwriting/deleting data however this has yet to be seen. Ensure FILESYSTEM_DRIVER is set to "s3" and define all AWS .env variables

## Mandrill
Mandrill is used to send emails to Users. When in a non production environment all emails will be directed to the email address defined in .env LOCAL_EMAIL_TO 

## Etsy
The Etsy integration performs the following functions:
* Import Etsy Orders
  - Command: `php artisan etsy:import-orders`
* Import Etsy Products
  - Command: `php artisan etsy:import-products`
* Create Products in Etsy
  - Command: Handled through queue listener
* Fulfill Etsy Orders
  - Command: Handled through queue listener when OrderDesk webhook is received
### Setup
* Ensure all ETSY .env vars are set, if you need API keys or secrets contact Eduardo or Jaret
* Create an Etsy shop
* Set your shop to Developer mode at https://www.etsy.com/developers/shop
* Navigate to teelaunch app > Stores and click Add Store then select Etsy
* You will be directed through the Oauth process after which you will be redirected to the teelaunch app Stores view with a success message
* If it fails to install ensure you are using HTTPS  
* *NOTE: Etsy may require a credit card on file for some functionality, Chris can supply one*

## Shopify
The Shopify integration performs the following functions:
* Import Shopify Orders
  - Command: `php artisan shopify:import-orders`
* Import Shopify Products
  - Command: `php artisan shopify:import-orders`
* Create Products in Shopify
  - Command: Handled through queue listener
* Fulfill Shopify Orders
  - Command: Handled through queue listener when OrderDesk webhook is received
### Setup
* Configure an ngrok URL to expose your environment to the internet
* Navigate to the Shopify partners account > Apps and Create a Custom Shopify app
* Set App URL to (replace NGROK_URL with your URL): `NGROK_URL/shopify/app`
* Set Allowed redirection URLs to (replace NGROK_URL with your URL): `NGROK_URL/shopify/app/install`
* Add the Shopify API key and secret to the .env
* Update your .env `APP_URL` to use the ngrok URL 
* Start ngrok
* In Shopify App view click Test on Development Store and select the store you want to install the app on
* You will be directed through the Oauth process after which you will be presented with the teelaunch app embedded in the Shopify store you selected

## OrderDesk
The OrderDesk integration performs the following functions:  
* Charge Orders and Send Orders to OrderDesk
  - Command: `php artisan orderdesk:export-orders`
* Receive Shipment Data from OrderDesk webhook
  - Command: Handled through queue listener when OrderDesk webhook is received
### Setup
* Create an Orderdesk account or ask Chris for access to teelaunch app Sandbox account
* Navigate to OrderDesk > Store Settings > API and enter the API key and store id into your .env
* Setup the following Order Rules
  * Order Split Count Rule *(don't duplicate this rule if it already exists)*
    * Filters: Order Metadata Field source Equals teelaunch-2
    * Trigger: Items Removed From Order After Split
    * Action: Set Order Metadata Value
    * Field Name: order_split_total
    * Field Value: {{order_metadata['order_split_total'] + 1}}
  * Order Split Count Webhook
    * Filters: Order Metadata Field source Equals teelaunch-2
    * Trigger: Items Removed From Order After Split
    * Action: POST Order JSON
    * Destination (replace NGROK_URL): NGROK_URL/api/v1/orderdesk/hooks/order-split
  * Order Shipment Webhook
    * Filters: Order Metadata Field source Equals teelaunch-2
    * Trigger: Shipment is Added
    * Action: POST Order JSON
    * Destination (replace NGROK_URL): NGROK_URL/api/v1/orderdesk/hooks/shipments

## Stripe
The Stripe integration performs the following functions:
* Setup Stripe Customer
* Charge Stripe Customer
  - Stripe is charged during the "Send Orders to OrderDesk" process
### Setup
* Ensure all `STRIPE` .env vars are set, if you need API keys or secrets contact eduardo@sunriseintegration or jaret@teelaunch.com
* Navigate to teelaunch app > Account > Billing and select Stripe
* Enter 4242424242424242 for card number
* Use a valid date in the futer for expiration and any 3 digits for CCV
* Enter address and save

## PayPal/Braintree
The PayPal/Braintree integration performs the following functions:
* Setup PayPal Customer
* Charge PayPal Customer
  - PayPal is charged during the "Send Orders to OrderDesk" process
### Setup
* Ensure all `BRAINTREE` .env vars are set, if you need API keys or secrets contact eduardo@sunriseintegration or jaret@teelaunch.com
* Navigate to teelaunch app > Account > Billing and select PayPal
* Click PayPal Checkout button
* You will be presented with a PayPal Login Form
* Sandbox Username: `sb-xxk5c3463056@personal.example.com`
* Sandbox Pass: `?t0-6z%D`
* Select a Payment Method
* When PayPal window closes click Continue with PayPal

## Payoneer
	* Anthony please fill out

# Models
* Models are named for ease of understanding how data relates to one another. When a Model belongs to another Model the table will typically use the parent Model name as a prefix: i.e. AccountSettings belongs to Account
* Model properties are named using the conventional Laravel naming scheme: i.e. A foreign key referencing Accounts would be named `account_id`

## Account Models
### Accounts
**ATTENTION: When querying Account specific data from non web or api auth routes the `WHERE account_id = account_id` query must be added manually.** 
On registration a new Account and User is created. Account ids are used to isolate data and prevent other Accounts and Users from accessing a different Account's data. Models with an account_id typically have an Account Global Scope defined which automatically adds `WHERE account_id = account_id` to the query when called through a web auth or api auth route. The primary User is defined in the user_id field.

### Users
Users belong to Accounts. Users must verify their email address prior to gaining full access to the app. Passwords are hashed for security.

### AdminUsers
AdminUsers are only allowed to access the admin site and do not exist as Users to the app

### AccountImages
Images that a User has uploaded to their Account for reference when creating Products

## Order Models
### Orders
Orders follow the following flow:
1. Customer places Order on Platform Store or creates one within teelaunch app
2. Order is imported into teelaunch
3. Order is processed, charged and sent to OrderDesk
4. OrderDesk sends shipment information when an Order is marked shipped
5. teelaunch fulfills Order in Platform Store

Orders have a discrete status (OrderStatus class) that they move through allowing the system to know what steps have been completed on an order. With the exception of `PROCESSING_` statuses, Order statuses should never decrement in value and should only increment. Statuses prefixed with `PROCESSING_` are transitional statuses and indicate that a process is either currently handling the Order, or the process failed without reverting the status. Any Orders with a has_error flag set to true will not process, and require the User to clear the error through the teelaunch app Orders or Order view.

All PlatformStore Orders may be imported with the following command: `php artisan platforms:import-orders`

## Product Models
### Products & ProductsVariants
Product and ProductVariants are the end result of when a User creates a new Product on the teelaunch app. ProductVariants are a representation of a BlankVariant and it's associated image files. 

### FileModels 
All Models that store a file extend the FileModel class. This class contains several helper function for storing files. Of note is the saveFile method. When creating a new FileModel, you will need to first create the DB row and then may call either `saveFileFromRequest`,`saveFileFromURL` or `saveFile` on the Model, all of which will upload the file to AWS S3 and save related data to the DB row.

### ProductArtFile (FileModel)
Relates an AccountImage to a Product

### ProductMockupFile (FileModel)
Resulting file from ProcessProductMockupFile

### ProductPrintFile (FileModel)
Resulting file from ProcessProductPrintFile

### ProductVariantStageFile (FileModel)
Relates an ProductArtFile to a ProductVariant

### ProductVariantMockupFile (FileModel)
Relates an ProductMockupFile to a ProductVariant

### ProductVariantPrintFile (FileModel)
Relates an ProductPrintFile to a ProductVariant

## Platform Models
### Platforms
Platforms represent the various Integrations that an Account may connect to

### PlatformStores
Represents an Account Store/Integration 

### PlatformStoreSettings
**Highly sensitive data**
Contains api tokens and data needed to connect to a PlatformStore and process its data. API tokens and secrets stored here must be encrypted when stored and decrypted when retrieved.

### PlatformStoreProducts
Represents a product that exists on a PlatformStore. PlatformStoreProducts are created when a Platform's products are imported into teelaunch or when creating a new product on a Platform. All PlatformStores may be synced with the command: `php artisan platforms:import-products`

### PlatformStoreProductVariants
Represents a product variant that exists on a PlatformStore

### PlatformStoreProductVariantMappings
Relates a PlatformStoreProductVariant to a ProductsVariant

## Payment Models
### PaymentMethod
Contains teelaunch Payment processors

### AccountPaymentMethod
Represents the connection between an Account and a PaymentMethod

### AccountPaymentMethodMetadata
**Highly sensitive data**
Contains data required to interact with a Payment Processors API and associated information. All values are automatically encrypted and decrypted. Care must be taken when accessing this table as the information is highly sensitive. For this purpose accesing this Model should only be done through well defined query scopes.

### AccountPayment
Represents a payment the Account has made. The AccountPayment will typically cover several Orders. Status may be either `ChargeDetails::STATUS_SUCCESS` or `ChargeDetails::STATUS_FAILED`. *(Note: this Model may eventually be updated to handle refunds in the future, in which case negative values will be used)*

### OrderAccountPayment
Relates an Order to an AccountPayment and stores a total of all OrderAccountPaymentLineItems on the Order. 

### OrderAccountPaymentLineItems
Relates an OrderLineItem to an OrderAccountPayment and the costs that were used in the OrderAccountPayment

## Blank Models
### Blanks
A Blank represents a raw unfinished product that teelaunch sells (i.e. a blank t-shirt with no art)

### BlankVariants
BlankVariants represent the variations of a Blank (i.e. a blank small black t-shirt with no art)

### BlankVariantOptionValues
Relates BlankVariant to BlankOptionValue

### BlankVariantMetadata (IGNORE)
Not currently in use

### BlankVariantImageOptions
Relates a Blank to a BlankOption. Defines which BlankOption to use when accessing BlankVariant images to display (i.e. if a Blank is set to use BlankOption "Color" the app will load new images when tabbing through Colors on the Product Builder)

### BlankVariantBlankImages
Relates BlankVariant to a BlankImage

### BlankStages
Relates a BlankStageGroup to a BlankStageLocation. Defines a "Stage/Canvas" that may be printed on.

### BlankStageLocations
Available locations for BlankStages. (i.e. Front Shirt, Back Shirt)

### BlankStageLocationSubs
Available sub-locations within a BlankStageLocation (i.e. Left Chest, Full Print)

### BlankStageLocationSubSettings
Relates BlankStage to BlankStageLocationSub

### BlankStageLocationSubSettingPreviews
Defines image positioning used when previewing a Product in Product Builder

### BlankStageLocationSubSettingOffsets
Defines image offset positioning used when previewing a Product in Product Builder and creating PrintFiles and MockupFiles

### BlankStageLocationSubOffsets
Available offsets

### BlankStageImageRequirements
Defines the image requirements for a BlankStage. Checked when a User selects an image to use on a Product.

### BlankStageGroups
BlankStageGroups allows grouping of BlankStages for UI purposes

### BlankStageCreateTypes
Available modes for creating a Product in Product Builder

### BlankStageCreateTypeBlankStages
Defines which BlankStageCreateTypes are available on a BlankStage.

### BlankPSDs
Defines a PSD to be used when creating a ProductMockupFile for a Blank of BlankOptionValue

### BlankPSDLayers
Defines layers on a BlankPSD to be used when creating a ProductMockupFile

### BlankPrintImages
Defines a PrintFile to be used when creating a ProductPrintFile for a Blank of BlankOptionValue and BlankStageGroup 

### BlankOptions
Available options to assign to a Blank

### BlankOptionValues
Available values that exist on a BlankOption

### BlankMetadata 
Stores metadata needed when processing a Blank

### BlankImages
Represents an image to be referenced by a Blank or BlankVariant

### BlankImageTypes
Defines available types to be referenced by BlankImages

### BlankCategories
Defines available categories that a Blank may reference

### BlankBlankOptions
Relates a Blank to a BlankOption

### BlankBlankImages
Relates a Blank to a BlankImage

