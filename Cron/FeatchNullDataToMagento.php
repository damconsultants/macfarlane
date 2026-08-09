<?php

namespace DamConsultants\Macfarlane\Cron;

use Exception;
use \Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Macfarlane\Model\BynderFactory;
use DamConsultants\Macfarlane\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Macfarlane\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;

class FeatchNullDataToMagento
{
    /**
     * Media "type" values as returned inside the Bynder API response data.
     * NOTE: video1 / document1 are taken from the strings the old code was
     * already checking for but never actually reaching. If your API sends
     * plain "video" / "document" instead, just change these two constants.
     */
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video1';
    const TYPE_DOCUMENT = 'document1';

    /**
     * @var $logger
     */
    protected $logger;
    /**
     * @var $_productRepository
     */
    protected $_productRepository;
    /**
     * @var $collectionFactory
     */
    protected $collectionFactory;
    /**
     * @var $datahelper
     */
    protected $datahelper;
    /**
     * @var $action
     */
    protected $action;
    /**
     * @var $_byndersycData
     */
    protected $_byndersycData;
    /**
     * @var $metaPropertyCollectionFactory
     */
    protected $metaPropertyCollectionFactory;
    /**
     * @var $bynderMediaTable
     */
    protected $bynderMediaTable;
    /**
     * @var $bynderMediaTableCollectionFactory
     */
    protected $bynderMediaTableCollectionFactory;
    /**
     * @var $storeManagerInterface
     */
    protected $storeManagerInterface;
    /**
     * @var $bynder
     */
    protected $bynder;

    /**
     * Featch Null Data To Magento
     * @param LoggerInterface $logger
     * @param ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param \DamConsultants\Macfarlane\Helper\Data $DataHelper
     * @param \DamConsultants\Macfarlane\Model\BynderSycDataFactory $byndersycData
     * @param \DamConsultants\Macfarlane\Model\BynderMediaTableFactory $bynderMediaTable
     * @param BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory
     * @param Action $action
     * @param MetaPropertyCollectionFactory $metaPropertyCollectionFactory
     * @param BynderFactory $bynder
     */
    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Macfarlane\Helper\Data $DataHelper,
        \DamConsultants\Macfarlane\Model\BynderSycDataFactory $byndersycData,
        \DamConsultants\Macfarlane\Model\BynderMediaTableFactory $bynderMediaTable,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        BynderFactory $bynder
    ) {
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->collectionFactory = $collectionFactory;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->_byndersycData = $byndersycData;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->bynder = $bynder;
    }

    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/FeatchNullDataToMagento.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("FeatchNullDataToMagento");

        $enable = $this->datahelper->getFetchCronEnable();
        if (!$enable) {
            return false;
        }
        $logger->info("yes");

        $product_collection = $this->collectionFactory->create();
        $product_sku_limit = (int)$this->datahelper->getProductSkuLimitConfig();
        $product_collection->getSelect()->limit(!empty($product_sku_limit) ? $product_sku_limit : 50);

        $product_collection->addAttributeToSelect('*')
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_multi_img', 'null' => true]
                ]
            )
            ->addAttributeToFilter(
                [
                    ['attribute' => 'bynder_cron_sync', 'null' => true]
                ]
            )
            ->load();

        $property_id = null;
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);

        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];

        $productSku_array = [];
        foreach ($product_collection->getData() as $product) {
            if (!empty($product['sku'])) {
                $productSku_array[] = $product['sku'];
            }
        }

        foreach ($productSku_array as $sku) {
            $bd_sku = $this->datahelper->replacetoSpecialString($sku);
            $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);

            if (empty($get_data) || !$this->getIsJSON($get_data)) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "message" => "Something problem in DAM side please contact to developer.",
                    "data_type" => "",
                    'media_id' => "",
                    'remove_for_magento' => '',
                    'added_on_cron_compactview' => '',
                    "lable" => "0"
                ]);
                continue;
            }

            $respon_array = json_decode($get_data, true);
            if ((int)$respon_array['status'] !== 1) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "message" => 'Please Select The Metaproperty First.....',
                    "data_type" => "",
                    'media_id' => "",
                    'remove_for_magento' => '',
                    'added_on_cron_compactview' => '',
                    "lable" => "0"
                ]);
                continue;
            }

            $convert_array = json_decode($respon_array['data'], true);
            if ((int)$convert_array['status'] !== 1) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "message" => $convert_array['data'],
                    "data_type" => "",
                    'media_id' => "",
                    'remove_for_magento' => '',
                    'added_on_cron_compactview' => '',
                    "lable" => "0"
                ]);
                $this->updateBynderCronSync($sku);
                continue;
            }

            try {
                // Type routing (image / video1 / document1) happens inside
                // getDataItem now, based on what the API actually sent back -
                // no more hardcoded "image only" filter here.
                $this->getDataItem($convert_array, $collection_slug_val, $sku);
            } catch (Exception $e) {
                $this->getInsertDataTable([
                    "sku" => $sku,
                    "message" => $e->getMessage(),
                    "data_type" => "",
                    'media_id' => "",
                    'remove_for_magento' => '',
                    'added_on_cron_compactview' => '',
                    "lable" => "0"
                ]);
                $this->updateBynderCronSync($sku);
            }
        }

        return true;
    }

    /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array $response_array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (count($collection) >= 1) {
            foreach ($collection as $key => $collection_value) {
                $collection_data_value[] = [
                    'id' => $collection_value['id'],
                    'property_name' => $collection_value['property_name'],
                    'property_id' => $collection_value['property_id'],
                    'magento_attribute' => $collection_value['magento_attribute'],
                    'attribute_id' => $collection_value['attribute_id'],
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                    'system_slug' => $collection_value['system_slug'],
                    'system_name' => $collection_value['system_name']
                ];
                $collection_data_slug_val[$collection_value['system_slug']] = [
                    'bynder_property_slug' => $collection_value['bynder_property_slug'],
                ];
            }
        }
        return [
            "collection_data_value" => $collection_data_value,
            "collection_data_slug_val" => $collection_data_slug_val
        ];
    }

    /**
     * Get current store id
     *
     * @return int
     */
    public function getMyStoreId()
    {
        return $this->storeManagerInterface->getStore()->getId();
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function getIsJSON($string)
    {
        return ((json_decode($string)) === null) ? false : true;
    }

    /**
     * Logs a sync event/error row.
     *
     * @param array $insert_data
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_byndersycData->create();
        $model->setData([
            'sku' => $insert_data['sku'],
            'bynder_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'media_id' => $insert_data['media_id'],
            'remove_for_magento' => $insert_data['remove_for_magento'],
            'added_on_cron_compactview' => $insert_data['added_on_cron_compactview'],
            'lable' => $insert_data['lable']
        ]);
        $model->save();
    }

    /**
     * Adds any media ids not already tracked for this SKU, and flags the
     * product for delete-cron follow up.
     *
     * @param string $sku
     * @param array $m_id
     * @param int $product_ids
     * @param int $storeId
     */
    public function getInsertMedaiDataTable($sku, $m_id, $product_ids, $storeId)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();

        $table_m_id = [];
        foreach ($modelcollection as $mdata) {
            $table_m_id[] = $mdata['media_id'];
        }

        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $model->setData([
                'sku' => $sku,
                'media_id' => trim($new_data),
                'status' => "1",
            ]);
            $model->save();
        }

        $this->action->updateAttributes(
            [$product_ids],
            ['bynder_delete_cron' => 1],
            $storeId
        );
    }

    /**
     * Removes bynder_media_table rows for this SKU whose media id is no
     * longer present in the current response.
     *
     * @param string $sku
     * @param array $media_ids
     */
    public function getDeleteMedaiDataTable($sku, $media_ids)
    {
        $media_ids = (array)$media_ids;
        $model = $this->bynderMediaTableCollectionFactory->create();
        $model->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        foreach ($model as $mdata) {
            if (!in_array($mdata['media_id'], $media_ids)) {
                $this->bynderMediaTable->create()->load($mdata['id'])->delete();
            }
        }
    }

    /**
     * Builds fresh image / video / document rows straight from the current
     * Bynder API response, routed by each item's own "type" field.
     *
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     */
    public function getDataItem($convert_array, $collection_data_slug_val, $current_sku)
    {
        if ((int)$convert_array['status'] === 0 || empty($convert_array['data'])) {
            return;
        }

        $image_items = [];
        $video_items = [];
        $doc_items = [];

        foreach ($convert_array['data'] as $data_value) {
            $type = isset($data_value['type']) ? $data_value['type'] : null;
            $thumbnails = isset($data_value['thumbnails']) ? $data_value['thumbnails'] : [];
            $bynder_media_id = isset($data_value['id']) ? $data_value['id'] : '';

            switch ($type) {
                case self::TYPE_IMAGE:
                    $image_items = array_merge(
                        $image_items,
                        $this->buildImageItems($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val)
                    );
                    break;

                case self::TYPE_VIDEO:
                    $video_items[] = $this->buildVideoItem($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val);
                    break;

                case self::TYPE_DOCUMENT:
                    $doc_items[] = $this->buildDocumentItem($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val);
                    break;

                default:
                    // Unknown/unsupported type from the API - skip it.
                    break;
            }
        }

        if (count($image_items) > 0 || count($video_items) > 0 || count($doc_items) > 0) {
            $this->getUpdateImage($current_sku, $image_items, $video_items, $doc_items);
        }
    }

    /**
     * One row per Magento image role for a single Bynder image asset
     * (an asset can map to several roles, each with its own derivative URL).
     *
     * @param array $data_value
     * @param array $thumbnails
     * @param string $bynder_media_id
     * @param array $collection_data_slug_val
     * @return array
     */
    private function buildImageItems($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val)
    {
        $items = [];
        $roles = isset($thumbnails['magento_role_options']) ? $thumbnails['magento_role_options'] : [];
        $order = $this->getOrderValue($data_value, $collection_data_slug_val);

        $alt_text_raw = isset($thumbnails['img_alt_text']) ? $thumbnails['img_alt_text'] : '';
        $alt_text = is_array($alt_text_raw) ? implode(" ", $alt_text_raw) : $alt_text_raw;

        if (count($roles) > 0) {
            foreach ($roles as $role) {
                $role_slug = ($role === "Base") ? "Base image" : $role;
                if (isset($thumbnails[$role_slug])) {
                    $url = $thumbnails[$role_slug];
                } elseif (isset($thumbnails["Product"])) {
                    $url = $thumbnails["Product"];
                } elseif (isset($thumbnails["webimage"])) {
                    $url = $thumbnails["webimage"];
                } else {
                    continue;
                }
                $items[] = $this->makeImageItem($url, $alt_text, [$role], $bynder_media_id, $order);
            }
        } elseif (isset($thumbnails["Product"])) {
            $items[] = $this->makeImageItem($thumbnails["Product"], $alt_text, [], $bynder_media_id, $order);
        }

        return $items;
    }

    /**
     * @param string $url
     * @param string $alt_text
     * @param array $role
     * @param string $bynder_media_id
     * @param string $order
     * @return array
     */
    private function makeImageItem($url, $alt_text, $role, $bynder_media_id, $order)
    {
        $thum_url = explode("?", $url)[0];
        return [
            "item_url" => $url,
            "alt_text" => $alt_text,
            "image_role" => $role,
            "item_type" => 'IMAGE',
            "thum_url" => $thum_url,
            "bynder_md_id" => $bynder_media_id,
            "is_import" => 0,
            "is_order" => $order
        ];
    }

    /**
     * @param array $data_value
     * @param array $thumbnails
     * @param string $bynder_media_id
     * @param array $collection_data_slug_val
     * @return array
     */
    private function buildVideoItem($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val)
    {
        return [
            "item_url" => isset($thumbnails['image_link']) ? $thumbnails['image_link'] : '',
            "image_role" => null,
            "item_type" => 'VIDEO',
            "thum_url" => isset($thumbnails['webimage']) ? $thumbnails['webimage'] : '',
            "bynder_md_id" => $bynder_media_id,
            "is_order" => $this->getOrderValue($data_value, $collection_data_slug_val)
        ];
    }

    /**
     * @param array $data_value
     * @param array $thumbnails
     * @param string $bynder_media_id
     * @param array $collection_data_slug_val
     * @return array
     */
    private function buildDocumentItem($data_value, $thumbnails, $bynder_media_id, $collection_data_slug_val)
    {
        $doc_name = isset($data_value['name']) ? $data_value['name'] : '';
        $doc_name_slug = preg_replace("/[^a-zA-Z]+/", "-", $doc_name);
        $item_url = (isset($thumbnails['image_link']) ? $thumbnails['image_link'] : '') . '@@' . $doc_name_slug;

        return [
            "item_url" => $item_url,
            "item_type" => 'DOCUMENT',
            "bynder_md_id" => $bynder_media_id,
            "is_order" => $this->getOrderValue($data_value, $collection_data_slug_val)
        ];
    }

    /**
     * @param array $data_value
     * @param array $collection_data_slug_val
     * @return string
     */
    private function getOrderValue($data_value, $collection_data_slug_val)
    {
        if (!isset($collection_data_slug_val['image_order']['bynder_property_slug'])) {
            return '';
        }
        $slug = "property_" . $collection_data_slug_val['image_order']['bynder_property_slug'];
        if (!isset($data_value[$slug])) {
            return '';
        }
        $value = $data_value[$slug];
        return is_array($value) ? implode(",", $value) : $value;
    }

    /**
     * Writes the freshly built media data onto the product. This cron only
     * ever selects products where bynder_multi_img is still NULL, so there
     * is nothing to merge with - the current API response is simply written
     * straight through.
     *
     * @param string $sku
     * @param array $image_items
     * @param array $video_items
     * @param array $doc_items
     */
    public function getUpdateImage($sku, $image_items, $video_items, $doc_items)
    {
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($sku);
            $product_ids = $_product->getId();

            $updated_values = [];

            if (count($image_items) > 0 || count($video_items) > 0) {
                $media_items = array_merge($image_items, $video_items);
                $media_ids = [];
                $types = [];

                foreach ($media_items as $item) {
                    $types[] = $item['item_type'];
                    $media_ids[] = $item['bynder_md_id'];

                    $this->getInsertDataTable([
                        "sku" => $sku,
                        "message" => $item['item_url'],
                        "data_type" => ($item['item_type'] === 'IMAGE') ? '1' : '3',
                        'media_id' => $item['bynder_md_id'],
                        'remove_for_magento' => '1',
                        'added_on_cron_compactview' => '1',
                        'lable' => 1
                    ]);
                }

                $flag = 0;
                if (in_array("IMAGE", $types) && in_array("VIDEO", $types)) {
                    $flag = 1;
                } elseif (in_array("IMAGE", $types)) {
                    $flag = 2;
                } elseif (in_array("VIDEO", $types)) {
                    $flag = 3;
                }

                $this->getDeleteMedaiDataTable($sku, $media_ids);
                $this->getInsertMedaiDataTable($sku, $media_ids, $product_ids, $storeId);

                $updated_values['bynder_multi_img'] = json_encode($media_items);
                $updated_values['bynder_isMain'] = $flag;
            }

            if (count($doc_items) > 0) {
                foreach ($doc_items as $doc) {
                    $this->getInsertDataTable([
                        "sku" => $sku,
                        "message" => $doc['item_url'],
                        "data_type" => '2',
                        'media_id' => $doc['bynder_md_id'],
                        'remove_for_magento' => '1',
                        'added_on_cron_compactview' => '1',
                        'lable' => 1
                    ]);
                }
                $updated_values['bynder_document'] = json_encode($doc_items);
            }

            if (count($updated_values) > 0) {
                $updated_values['bynder_cron_sync'] = 1;
                $updated_values['use_bynder_cdn'] = 1;
                $this->action->updateAttributes([$product_ids], $updated_values, $storeId);
            }
        } catch (Exception $e) {
            $this->getInsertDataTable([
                "sku" => $sku,
                "message" => $e->getMessage(),
                "data_type" => "",
                'media_id' => "",
                'remove_for_magento' => '',
                'added_on_cron_compactview' => '',
                "lable" => "0"
            ]);
        }
    }

    /**
     * Update Bynder cron sync status
     *
     * @param string $sku
     */
    public function updateBynderCronSync($sku)
    {
        $storeId = $this->getMyStoreId();
        $_product = $this->_productRepository->get($sku);
        $product_ids = $_product->getId();

        $this->action->updateAttributes(
            [$product_ids],
            ['bynder_cron_sync' => 2],
            $storeId
        );
    }
}