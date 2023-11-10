<?php

class AdminSyncSevDeskModuleController extends ModuleAdminController {


    public function initContent(){
        parent::initContent();

        $content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'mysyncsevdeskinvoices/views/templates/admin/index.tpl');
        $this->context->smarty->assign([
            'content' => $this->content . $content, // without $this->content before $content it will only display the content from initContent()
        ]);
    }

}