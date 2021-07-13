<?php
namespace DevAwesome\Breadcrumbs\Block;

use Magento\Catalog\Helper\Data;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Framework\Registry;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Framework\App\Request\Http;
use Magento\Catalog\Model\CategoryRepository;
use Psr\Log\LoggerInterface;

class Breadcrumbs extends \Magento\Framework\View\Element\Template
{
    /**
     * Catalog data
     *
     * @var Data
     */
    protected $_catalogData = null;

    /**
     * Registry data
     *
     * @var Registry
     */
    protected $_registry;

    /**
     * Redirect data
     *
     * @var RedirectInterface
     */
    protected $_redirect;

    /**
     * Category data
     *
     * @var CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * Request data
     *
     * @var Http
     */
    protected $_request;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $_logger;
    
    /**
     * @param Context $context
     * @param Data $catalogData
     * @param Registry $registry
     * @param RedirectInterface $redirect
     * @param CategoryFactory $categoryFactory
     * @param Http $request
     * @param CategoryRepository $categoryRepository
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $catalogData,
        Registry $registry,
        RedirectInterface $redirect,
        CategoryFactory $categoryFactory,
        Http $request,
        CategoryRepository $categoryRepository,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->_catalogData = $catalogData;
        $this->_registry = $registry;
        $this->_redirect = $redirect;
        $this->_categoryFactory = $categoryFactory;
        $this->_request = $request;
        $this->_categoryRepository = $categoryRepository;
        $this->_logger = $logger;
        parent::__construct($context, $data);
    }
    
    /**
     * @return array $breadcrumbs
     */
    public function getCrumbs()
    {
        $breadcrumbs = [];

        $breadcrumbs[] = [
            'label' => 'Home',
            'title' => 'Go to Home Page',
            'link' => $this->_storeManager->getStore()->getBaseUrl(),
            'class' => 'home'
        ];

        // Resolve current category
        $category = $this->_registry->registry('current_category');// using registry
        $categoryId = $this->_request->getParam('category');// using request param 
        $refererUrl = $this->_redirect->getRefererUrl();// using previous page url

        $categoryPath = "";

        if(!$category){
            try {
                if($categoryId){
                    $categoryPath = $this->_categoryRepository->get($categoryId,$this->_storeManager->getStore()->getId())->getPath();
                }elseif($refererUrl){
                    $categoryUrl = substr($refererUrl, strrpos($refererUrl, '/') + 1);
                    $categoryUrlKey = str_replace('.html', '', $categoryUrl) ;
                    $urlCategory = $this->_categoryFactory->create()->getCollection()
                        ->addAttributeToFilter('url_key', $categoryUrlKey)
                        ->addAttributeToSelect(['path'])
                        ->getFirstItem();
                }else{
                    $categoryPath = "1/" . $this->_storeManager->getStore()->getRootCategoryId() . "/%"; 
                }

                $product = $this->_registry->registry('current_product');
                if($urlCategory){
                    $breadcrumbCategories = $urlCategory->getParentCategories();
                }else{
                    $categoryCollection = clone $product->getCategoryCollection();
                    $categoryCollection->clear();
                    $categoryCollection->addAttributeToSort('level', $categoryCollection::SORT_ORDER_DESC);
                    $categoryCollection->addAttributeToFilter('path',['like' => $categoryPath]);
                    $categoryCollection->setPageSize(1);
                    $breadcrumbCategories = $categoryCollection->getFirstItem()->getParentCategories();
                }
                
                usort($breadcrumbCategories, function ($item1, $item2) {
                    return $item1['path'] <=> $item2['path'];
                });
                
                foreach ($breadcrumbCategories as $category) {
                    $breadcrumbs[] = [
                        'label' => $category->getName(),
                        'title' => $category->getName(),
                        'link' => $category->getUrl(),
                        'class' => 'category'
                    ];
                }
            } catch (Exception $e) {
                $this->_logger->debug($e->getMessage());
            }

            $breadcrumbs[] = [
                'label' => $product->getName(),
                'title' => $product->getName(),
                'link' => '',
                'class' => 'product'
            ];
        }else{
            $path = $this->_catalogData->getBreadcrumbPath();
            foreach ($path as $key => $crumb) {
                $breadcrumbs[] = [
                    'label' => $crumb['label'],
                    'title' => $crumb['label'],
                    'link' => isset($crumb['link']) ? $crumb['link'] : '',
                    'class' => ''
                ];
            }
        }

        return $breadcrumbs;
    }
}