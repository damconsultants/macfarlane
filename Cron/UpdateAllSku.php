<?php

namespace DamConsultants\Macfarlane\Cron;

use Exception;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\Product\Action;
use DamConsultants\Macfarlane\Model\ResourceModel\Collection\MetaPropertyCollectionFactory;
use DamConsultants\Macfarlane\Model\ResourceModel\Collection\BynderMediaTableCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\MagentoSkuCollectionFactory;
use DamConsultants\Ahfproducts\Model\ResourceModel\MagentoSku;
use Magento\Framework\App\ResourceConnection;

/**
 * Cron: processes the queue of pending SKUs and pushes Bynder media data
 * onto the matching Magento products.
 *
 * Structured after DamConsultants\Ahfproducts\Cron\UpdateAllSku (batch loop
 * over a pending queue, per-row try/catch, log table writes) but:
 *  - drops all alias-sku handling (Macfarlane has no alias concept)
 *  - the actual data-building logic (getDataItem / getProcessItem /
 *    getUpdateImage) is carried over from
 *    DamConsultants\Macfarlane\Controller\Adminhtml\Index\Psku, since that
 *    is the logic this module already relies on for `select_attribute`
 *    driven (image / video / document) syncing.
 *
 * A pending queue row is only deleted once the sync for that SKU actually
 * succeeded. getUpdateImage/getProcessItem/getDataItem all return a bool so
 * a failure inside them (e.g. product not found, attribute save error)
 * bubbles back up to execute() instead of being silently swallowed - see
 * the try/catch inside getUpdateImage, which used to log-and-eat the error
 * without telling the caller anything went wrong.
 */
class UpdateAllSku
{
    /** @var LoggerInterface */
    protected $logger;
    /** @var ProductRepository */
    protected $_productRepository;
    /** @var StoreManagerInterface */
    protected $storeManagerInterface;
    /** @var \DamConsultants\Macfarlane\Helper\Data */
    protected $datahelper;
    /** @var Action */
    protected $action;
    /** @var MetaPropertyCollectionFactory */
    protected $metaPropertyCollectionFactory;
    /** @var \DamConsultants\Macfarlane\Model\BynderMediaTableFactory */
    protected $bynderMediaTable;
    /** @var BynderMediaTableCollectionFactory */
    protected $bynderMediaTableCollectionFactory;
    /** @var \DamConsultants\Macfarlane\Model\BynderConfigSyncDataFactory */
    protected $_byndersycData;
    protected $magentoSkuCollectionFactory;
    protected $resourceConnection;
    protected $magentoSku;

    public function __construct(
        LoggerInterface $logger,
        ProductRepository $productRepository,
        StoreManagerInterface $storeManagerInterface,
        \DamConsultants\Macfarlane\Helper\Data $DataHelper,
        \DamConsultants\Macfarlane\Model\BynderMediaTableFactory $bynderMediaTable,
        \DamConsultants\Macfarlane\Model\BynderConfigSyncDataFactory $byndersycData,
        BynderMediaTableCollectionFactory $bynderMediaTableCollectionFactory,
        MagentoSkuCollectionFactory $magentoSkuCollectionFactory,
        MagentoSku $magentoSku,
        Action $action,
        MetaPropertyCollectionFactory $metaPropertyCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
        $this->_productRepository = $productRepository;
        $this->datahelper = $DataHelper;
        $this->action = $action;
        $this->metaPropertyCollectionFactory = $metaPropertyCollectionFactory;
        $this->bynderMediaTable = $bynderMediaTable;
        $this->magentoSkuCollectionFactory = $magentoSkuCollectionFactory;
        $this->bynderMediaTableCollectionFactory = $bynderMediaTableCollectionFactory;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->_byndersycData = $byndersycData;
        $this->resourceConnection = $resourceConnection;
        $this->magentoSku = $magentoSku;
    }

    /**
     * Execute
     *
     * @return boolean
     */
    public function execute()
    {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/UpdateAllSku.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info('UpdateAllSku cron started.');

        $enable = $this->datahelper->getUpdateAllSkuCronEnable();
        if (!$enable) {
            $logger->info('UpdateAllSku cron disabled via config, exiting.');
            return false;
        }

        $skuQueueCollection = $this->magentoSkuCollectionFactory->create();
        $skuQueueCollection->addFieldToFilter('status', 'pending')->setPageSize(100);

        if ($skuQueueCollection->getSize() === 0) {
            $logger->info('No pending SKUs to process.');
            return true;
        }

        $property_id = null;
        $collection = $this->metaPropertyCollectionFactory->create()->getData();
        $meta_properties = $this->getMetaPropertiesCollection($collection);
        $collection_value = $meta_properties['collection_data_value'];
        $collection_slug_val = $meta_properties['collection_data_slug_val'];

        foreach ($skuQueueCollection as $queueRow) {
            $sku = $queueRow['sku'];
            if ($sku == '') {
                continue;
            }

            $select_attribute = $queueRow['select_attribute'];
            $this->ensureDbConnection();
            try {
                $bd_sku = $this->datahelper->replacetoSpecialString($sku);
                $get_data = $this->datahelper->getImageSyncWithProperties($bd_sku, $property_id, $collection_value);

                if (empty($get_data) || !$this->getIsJSON($get_data)) {
                    $this->getInsertDataTable([
                        'sku' => $sku,
                        'message' => 'Something went wrong from API side, Please contact to support team!',
                        'data_type' => '',
                        'lable' => '0'
                    ]);
                    $this->deleteQueueRow($queueRow);
                    continue;
                }

                $respon_array = json_decode($get_data, true);
                if ((int)$respon_array['status'] !== 1) {
                    $this->getInsertDataTable([
                        'sku' => $sku,
                        'message' => 'Please Select The Metaproperty First.....',
                        'data_type' => '',
                        'lable' => '0'
                    ]);
                    $this->deleteQueueRow($queueRow);
                    continue;
                }

                $convert_array = json_decode($respon_array['data'], true);
                if ((int)$convert_array['status'] !== 1) {
                    $this->getInsertDataTable([
                        'sku' => $sku,
                        'message' => $convert_array['data'],
                        'data_type' => '',
                        'lable' => '0'
                    ]);
                    $this->deleteQueueRow($queueRow);
                    continue;
                }

                $synced = $this->getDataItem($select_attribute, $convert_array, $collection_slug_val, $sku);
                if ($synced) {
                    $this->deleteQueueRow($queueRow);
                } else {
                    $logger->info('UpdateAllSku: sync did not complete successfully for SKU ' . $sku . ', leaving queued for retry.');
                }
            } catch (Exception $e) {
                $logger->info('UpdateAllSku error for SKU ' . $sku . ': ' . $e->getMessage());
                if ($this->isConnectionLostError($e)) {
                    $logger->info('UpdateAllSku: DB connection lost, reconnecting and skipping SKU ' . $sku . ' for retry next run.');
                    $this->ensureDbConnection();
                    continue;
                }
                $this->getInsertDataTable([
                    'sku' => $sku,
                    'message' => $e->getMessage(),
                    'data_type' => '',
                    'lable' => '0'
                ]);
                $this->deleteQueueRow($queueRow);
            }
        }

        $logger->info('UpdateAllSku cron completed.');
        return true;
    }

    /**
     * Removes a processed row from the pending queue so it is not picked
     * up again on the next run.
     *
     * @param \Magento\Framework\DataObject $queueRow
     * @return void
     */
    protected function deleteQueueRow($queueRow)
    {
        try {
            $this->magentoSku->delete($queueRow);
            //$this->bynderMediaTable->create()->load($queueRow->getId())->delete();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Pings the DB connection and forces a reconnect if it has died. Cheap
     * to call before every SKU since it's a single SELECT 1.
     *
     * @return void
     */
    protected function ensureDbConnection()
    {
        try {
            $this->resourceConnection->getConnection()->query('SELECT 1');
        } catch (Exception $e) {
            $this->reconnectDb();
        }
    }

    /**
     * Forces the connection to be dropped so Magento re-establishes it on
     * the next query, rather than continuing to reuse a dead one.
     *
     * @return void
     */
    protected function reconnectDb()
    {
        try {
            $this->resourceConnection->closeConnection();
        } catch (Exception $e) {
            // Closing an already-dead connection can itself throw - safe to
            // ignore, the next getConnection()->query() call will reconnect.
        }
    }

    /**
     * Whether an exception looks like a dropped/unreachable MySQL
     * connection (as opposed to a real data/logic error worth keeping in
     * the sync log).
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function isConnectionLostError($e)
    {
        $message = $e->getMessage();
        return (
            strpos($message, 'MySQL server has gone away') !== false
            || strpos($message, '2006') !== false
            || strpos($message, '2002') !== false
            || strpos($message, 'Lost connection') !== false
            || strpos($message, 'Error while sending') !== false
        );
    }

    /**
     * Get Meta Properties Collection
     *
     * @param array $collection
     * @return array
     */
    public function getMetaPropertiesCollection($collection)
    {
        $collection_data_value = [];
        $collection_data_slug_val = [];
        if (count($collection) >= 1) {
            foreach ($collection as $collection_value) {
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
            'collection_data_value' => $collection_data_value,
            'collection_data_slug_val' => $collection_data_slug_val
        ];
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function getIsJSON($string)
    {
        if ($string === null || $string === '') {
            return false;
        }
        return ((json_decode($string)) === null) ? false : true;
    }

    /**
     * Insert Data Table
     *
     * @param array $insert_data
     * @return void
     */
    public function getInsertDataTable($insert_data)
    {
        $model = $this->_byndersycData->create();
        $data_image_data = [
            'sku' => $insert_data['sku'],
            'bynder_sync_data' => $insert_data['message'],
            'bynder_data_type' => $insert_data['data_type'],
            'lable' => $insert_data['lable']
        ];
        $model->setData($data_image_data);
        $model->save();
    }

    /**
     * Insert Media Data Table
     *
     * @param string $sku
     * @param array $m_id
     * @param string $product_ids
     * @param string $storeId
     * @return void
     */
    public function getInsertMedaiDataTable($sku, $m_id, $product_ids, $storeId)
    {
        $model = $this->bynderMediaTable->create();
        $modelcollection = $this->bynderMediaTableCollectionFactory->create();
        $modelcollection->addFieldToFilter('sku', ['eq' => [$sku]])->load();
        $table_m_id = [];
        if (!empty($modelcollection)) {
            foreach ($modelcollection as $mdata) {
                $table_m_id[] = $mdata['media_id'];
            }
        }
        $media_diff = array_diff($m_id, $table_m_id);
        foreach ($media_diff as $new_data) {
            $new_m_id = trim($new_data);
            $model->setData([
                'sku' => $sku,
                'media_id' => $new_m_id,
                'status' => '1',
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
     * @return void
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
     * Get Data Item
     *
     * Carried over from Psku::getDataItem - builds the newline-delimited
     * url / role / alt-text / media-id / order strings for the
     * select_attribute (image / video / document) that was queued for
     * this SKU.
     *
     * @param string $select_attribute
     * @param array $convert_array
     * @param array $collection_data_slug_val
     * @param string $current_sku
     * @return bool true if the SKU was actually synced to the product
     */
    public function getDataItem($select_attribute, $convert_array, $collection_data_slug_val, $current_sku)
    {
        $data_arr = [];
        $data_val_arr = [];

        if ($convert_array['status'] == 1) {
            foreach ($convert_array['data'] as $data_value) {
                $is_order = [];
                if ($select_attribute == $data_value['type']) {
                    $bynder_media_id = $data_value['id'];
                    $image_data = $data_value['thumbnails'];
                    $bynder_image_role = $image_data['magento_role_options'];
                    $data_sku[0] = $current_sku;
                    $images_urls_list = [];
                    $new_magento_role_list = [];
                    $new_bynder_alt_text = [];
                    $new_bynder_mediaid_text = [];

                    if (count($bynder_image_role) > 0) {
                        foreach ($bynder_image_role as $m_bynder_role) {
                            $original_m_bynder_role = $m_bynder_role;
                            $original_m_bynder_role_slug = ($m_bynder_role == 'Base') ? 'Base image' : $m_bynder_role;

                            if (isset($data_value['thumbnails'][$original_m_bynder_role_slug])) {
                                $images_urls_list[] = $data_value['thumbnails'][$original_m_bynder_role_slug] . "\n";
                                $new_magento_role_list[] = $original_m_bynder_role . "\n";

                                $alt_text_vl = $data_value['thumbnails']['img_alt_text'];
                                if (is_array($data_value['thumbnails']['img_alt_text'])) {
                                    $alt_text_vl = implode(' ', $data_value['thumbnails']['img_alt_text']);
                                }
                                $new_bynder_alt_text[] = (strlen($alt_text_vl) > 0) ? $alt_text_vl . "\n" : "###\n";
                            } else {
                                if (isset($data_value['thumbnails']['Product'])) {
                                    $images_urls_list[] = $data_value['thumbnails']['Product'] . "\n";
                                } else {
                                    $images_urls_list[] = $data_value['thumbnails']['webimage'] . "\n";
                                }

                                $new_magento_role_list[] = $original_m_bynder_role . "\n";
                                $alt_text_vl = $data_value['thumbnails']['img_alt_text'];
                                if (is_array($data_value['thumbnails']['img_alt_text'])) {
                                    $alt_text_vl = implode(' ', $data_value['thumbnails']['img_alt_text']);
                                }
                                $new_bynder_alt_text[] = (strlen($alt_text_vl) > 0) ? $alt_text_vl . "\n" : "###\n";
                            }
                            $new_bynder_mediaid_text[] = $bynder_media_id . "\n";
                            $magento_order_slug = 'property_' . $collection_data_slug_val['image_order']['bynder_property_slug'];
                            if (isset($data_value[$magento_order_slug])) {
                                foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                    $is_order[] = $property_Magento_Media_Order . "\n";
                                }
                            }
                        }
                    } else {
                        $new_magento_role_list[] = "###\n";
                        $alt_text_vl = $data_value['thumbnails']['img_alt_text'];
                        $new_bynder_alt_text[] = !empty($alt_text_vl) ? $alt_text_vl . "\n" : "###\n";
                        $new_bynder_mediaid_text[] = $bynder_media_id . "\n";
                        $magento_order_slug = 'property_' . $collection_data_slug_val['image_order']['bynder_property_slug'];
                        if (isset($data_value[$magento_order_slug])) {
                            foreach ($data_value[$magento_order_slug] as $property_Magento_Media_Order) {
                                $is_order[] = $property_Magento_Media_Order . "\n";
                            }
                        }
                    }

                    if (count($images_urls_list) == 0) {
                        if (isset($image_data['Product'])) {
                            $images_urls_list[] = $image_data['Product'] . "\n";
                        } else {
                            $images_urls_list[] = "no image\n";
                        }
                    }

                    if ($data_value['type'] == 'image') {
                        array_push($data_arr, $data_sku[0]);
                        $data_val_arr[] = [
                            'sku' => $data_sku[0],
                            'url' => $images_urls_list,
                            'magento_image_role' => $new_magento_role_list,
                            'image_alt_text' => $new_bynder_alt_text,
                            'bynder_media_id_new' => $new_bynder_mediaid_text,
                            'is_order' => $is_order
                        ];
                    } elseif ($select_attribute == 'video') {
                        $video_link = $image_data['image_link'] . '@@' . $image_data['webimage'];
                        array_push($data_arr, $data_sku[0]);
                        $data_val_arr[] = ['sku' => $data_sku[0], 'url' => $video_link, 'is_order' => $is_order];
                    } else {
                        $doc_name = $data_value['name'];
                        $doc_name_with_space = preg_replace('/[^a-zA-Z]+/', '-', $doc_name);
                        $doc_link = $image_data['image_link'] . '@@' . $doc_name_with_space;
                        array_push($data_arr, $data_sku[0]);
                        $data_val_arr[] = ['sku' => $data_sku[0], 'url' => $doc_link, 'is_order' => $is_order];
                    }
                }
            }
        }

        if (count($data_arr) > 0) {
            return $this->getProcessItem($select_attribute, $data_arr, $data_val_arr);
        }

        $this->getInsertDataTable([
            'sku' => $current_sku,
            'message' => 'No Data Found...',
            'data_type' => '',
            'lable' => '0'
        ]);
        return false;
    }

    /**
     * Get Process Item
     *
     * Carried over from Psku::getProcessItem.
     *
     * @param string $select_attribute
     * @param array $data_arr
     * @param array $data_val_arr
     * @return bool true only if every SKU/group in this batch synced
     *              successfully
     */
    public function getProcessItem($select_attribute, $data_arr, $data_val_arr)
    {
        $image_value_details_role = [];
        $temp_arr = [];
        $byn_is_order = [];
        $image_alt_text = [];
        $byn_md_id_new = [];

        foreach ($data_arr as $key => $skus) {
            $temp_arr[$skus][] = implode('', $data_val_arr[$key]['url']);
            $image_value_details_role[$skus][] = implode('', $data_val_arr[$key]['magento_image_role'] ?? []);
            $image_alt_text[$skus][] = implode('', $data_val_arr[$key]['image_alt_text'] ?? []);
            $byn_md_id_new[$skus][] = implode('', $data_val_arr[$key]['bynder_media_id_new'] ?? []);
            $byn_is_order[$skus][] = implode('', $data_val_arr[$key]['is_order']);
        }

        $allSucceeded = true;
        foreach ($temp_arr as $product_sku_key => $image_value) {
            $img_json = implode('', $image_value);
            $mg_role = implode('', $image_value_details_role[$product_sku_key]);
            $image_alt_text_value = implode('', $image_alt_text[$product_sku_key]);
            $byd_media_id_value = implode('', $byn_md_id_new[$product_sku_key]);
            $byd_media_is_order = implode('', $byn_is_order[$product_sku_key]);

            $synced = $this->getUpdateImage(
                $select_attribute,
                $img_json,
                $product_sku_key,
                $mg_role,
                $image_alt_text_value,
                $byd_media_id_value,
                $byd_media_is_order
            );
            if (!$synced) {
                $allSucceeded = false;
            }
        }

        return $allSucceeded;
    }

    /**
     * Update Item. Writes the API response for the queued select_attribute
     * (image / video / document) straight onto the product - this always
     * replaces whatever is already stored in bynder_multi_img /
     * bynder_document with what the current sync just returned, same as
     * Psku::getUpdateImage.
     *
     * @param string $select_attribute
     * @param string $img_json
     * @param string $product_sku_key
     * @param string $mg_img_role_option
     * @param string $img_alt_text
     * @param string $bynder_media_ids
     * @param string $byd_media_is_order
     * @return bool true if the product attributes were actually updated
     */
    public function getUpdateImage($select_attribute, $img_json, $product_sku_key, $mg_img_role_option, $img_alt_text, $bynder_media_ids, $byd_media_is_order)
    {
        $image_detail = [];
        try {
            $storeId = $this->storeManagerInterface->getStore()->getId();
            $_product = $this->_productRepository->get($product_sku_key);
            $product_ids = $_product->getId();

            $bynder_media_id = explode("\n", $bynder_media_ids);
            $isOrder = explode("\n", $byd_media_is_order);

            if ($select_attribute == 'image') {
                $new_image_array = explode("\n", $img_json);
                $new_alttext_array = explode("\n", $img_alt_text);
                $new_magento_role_option_array = explode("\n", $mg_img_role_option);

                foreach ($new_image_array as $vv => $new_image_value) {
                    if (trim($new_image_value) != '' && $new_image_value != 'no image') {
                        $item_url = explode('?', $new_image_value);
                        $img_altText_val = '';
                        if (isset($new_alttext_array[$vv])) {
                            if ($new_alttext_array[$vv] != '###' && strlen(trim($new_alttext_array[$vv])) > 0) {
                                $img_altText_val = $new_alttext_array[$vv];
                            }
                        }

                        $curt_img_role = [];
                        if ($new_magento_role_option_array[$vv] != '###') {
                            $curt_img_role = [$new_magento_role_option_array[$vv]];
                        }

                        $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : '';

                        $image_detail[] = [
                            'item_url' => $new_image_value,
                            'alt_text' => $img_altText_val,
                            'image_role' => $curt_img_role,
                            'item_type' => 'IMAGE',
                            'thum_url' => $item_url[0],
                            'bynder_md_id' => $bynder_media_id[$vv],
                            'is_import' => 0,
                            'is_order' => $is_order
                        ];

                        $total_new_value = count($image_detail);
                        if ($total_new_value > 1) {
                            foreach ($image_detail as $nn => $n_img) {
                                if ($n_img['item_type'] == 'IMAGE' && $nn != ($total_new_value - 1)) {
                                    $new_mg_role_array = (array)$new_magento_role_option_array[$vv];
                                    if (count($n_img['image_role']) > 0 && count($new_mg_role_array) > 0) {
                                        $image_detail[$nn]['image_role'] = array_diff($n_img['image_role'], $new_mg_role_array);
                                    }
                                }
                            }
                        }
                    }
                }

                $media_id = [];
                $image = [];
                $type = [];
                foreach ($image_detail as $img) {
                    $type[] = $img['item_type'];
                    $image[] = $img['item_url'];
                    $media_id[] = $img['bynder_md_id'];
                }

                $this->getDeleteMedaiDataTable($product_sku_key, $media_id);
                $this->getInsertMedaiDataTable($product_sku_key, $media_id, $product_ids, $storeId);

                $image_value_array = implode(',', $image);
                $flag = 0;
                if (in_array('IMAGE', $type) && in_array('VIDEO', $type)) {
                    $flag = 1;
                } elseif (in_array('IMAGE', $type)) {
                    $flag = 2;
                } elseif (in_array('VIDEO', $type)) {
                    $flag = 3;
                }

                $new_value_array = json_encode($image_detail, true);
                $this->getInsertDataTable([
                    'sku' => $product_sku_key,
                    'message' => $image_value_array,
                    'data_type' => '1',
                    'lable' => '1'
                ]);

                $this->action->updateAttributes(
                    [$product_ids],
                    [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $flag,
                        'use_bynder_cdn' => 1
                    ],
                    $storeId
                );
            } elseif ($select_attribute == 'video') {
                $new_video_array = explode(" \n", $img_json);
                $video_detail = [];

                foreach ($new_video_array as $vv => $video_value) {
                    $item_url = explode('?', $video_value);
                    $thum_url = explode('@@', $video_value);
                    $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : '';
                    $video_detail[] = [
                        'item_url' => $item_url[0],
                        'image_role' => null,
                        'item_type' => 'VIDEO',
                        'thum_url' => isset($thum_url[1]) ? $thum_url[1] : '',
                        'bynder_md_id' => $bynder_media_id[$vv],
                        'is_order' => $is_order
                    ];
                }

                $type = [];
                foreach ($video_detail as $img) {
                    $type[] = $img['item_type'];
                }
                $flag = 0;
                if (in_array('IMAGE', $type) && in_array('VIDEO', $type)) {
                    $flag = 1;
                } elseif (in_array('IMAGE', $type)) {
                    $flag = 2;
                } elseif (in_array('VIDEO', $type)) {
                    $flag = 3;
                }

                $new_value_array = json_encode($video_detail, true);
                $this->getInsertDataTable([
                    'sku' => $product_sku_key,
                    'message' => $new_value_array,
                    'data_type' => '3',
                    'lable' => '1'
                ]);

                $this->action->updateAttributes(
                    [$product_ids],
                    [
                        'bynder_multi_img' => $new_value_array,
                        'bynder_isMain' => $flag,
                        'use_bynder_cdn' => 1
                    ],
                    $storeId
                );
            } else {
                $new_doc_array = explode("\n", $img_json);
                $doc_detail = [];

                foreach ($new_doc_array as $vv => $doc_value) {
                    if (trim($doc_value) == '') {
                        continue;
                    }
                    $item_url = explode('?', $doc_value);
                    $is_order = isset($isOrder[$vv]) ? $isOrder[$vv] : '';
                    $doc_detail[] = [
                        'item_url' => $item_url[0],
                        'item_type' => 'DOCUMENT',
                        'bynder_md_id' => $bynder_media_id[$vv],
                        'is_order' => $is_order
                    ];
                }

                $new_value_array = json_encode($doc_detail, true);
                $doc_urls = [];
                foreach ($doc_detail as $doc) {
                    $doc_urls[] = $doc['item_url'];
                }

                $this->getInsertDataTable([
                    'sku' => $product_sku_key,
                    'message' => implode(',', $doc_urls),
                    'data_type' => '2',
                    'lable' => '1'
                ]);

                $this->action->updateAttributes(
                    [$product_ids],
                    ['bynder_document' => $new_value_array],
                    $storeId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->getInsertDataTable([
                'sku' => $product_sku_key,
                'message' => $e->getMessage(),
                'data_type' => '',
                'lable' => '0'
            ]);
            return false;
        }
    }
}