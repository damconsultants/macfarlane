var config = {
    paths: {
        'bynderjs': 'DamConsultants_Macfarlane/js/bynder',
        'select2': 'DamConsultants_Macfarlane/js/select2'
    },
    shim: {
        'bynderjs': {
            deps: ['jquery']
        },
        'select2': {
            deps: ['jquery']
        },
    },
	map: {
        '*': {
            /*'Magento_PageBuilder/template/form/element/uploader/preview/image.html': 'DamConsultants_Macfarlane/template/form/element/uploader/preview/image.html',
            'Magento_PageBuilder/template/form/element/uploader/image.html': 'DamConsultants_Macfarlane/template/form/element/uploader/image.html',*/
            'Magento_PageBuilder/template/form/element/html-code.html': 'DamConsultants_Macfarlane/template/form/element/html-code.html',
            /*'Magento_PageBuilder/js/form/element/image-uploader': 'DamConsultants_Macfarlane/js/form/element/image-uploader',*/
            'Magento_PageBuilder/js/form/element/html-code': 'DamConsultants_Macfarlane/js/form/element/html-code',
        },
    }
};