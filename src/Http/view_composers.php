<?php

use Illuminate\View\View as ViewParam;

View::composer('admin::partials.navigation', function (ViewParam $view) {
    $user = Sentinel::getUser();
    $menu = config('builder.admin.menu');

    $view->with('user', $user)->with('menu', $menu);
});

View::composer(['admin::layouts.default', 'admin::partials.scripts'], function (ViewParam $view) {
    $skin = Cookie::get('skin') ?: 'smart-style-4';
    $thisLang = Cookie::get('lang_admin') ?: config('builder.translate_cms.lang_default');
    $customJs = config('builder.admin.custom_js');
    $customCss = config('builder.admin.custom_css');
    $logo = config('builder.admin.logo_url') ?: '/packages/vis/builder/img/logo.png';
    $logoWhite = config('builder.admin.logo_url_white') ?: '/packages/vis/builder/img/logo-w.png';

    if ($skin && $skin != 'smart-style-0') {
        $logo = $logoWhite;
    }

    $view->with(compact('skin', 'thisLang', 'customJs', 'customCss', 'logo'));
});

View::composer(['admin::tree.create_modal', 'admin::tree.content'], function (ViewParam $view) {
    $templates = config('builder.'.$view->treeName.'.templates');
    $model = config('builder.'.$view->treeName.'.model');
    $idNode = request('node', 1);
    //$html = '';

    $defaultDefinition = config('builder.'.$view->treeName.'.default_definition');

    /*if ($defaultDefinition) {
        $jarboeController = new \Vis\Builder\JarboeController([
            'url'      => URL::current(),
            'def_name' => $view->treeName.'.'.$defaultDefinition,
            'additional' => [
                'current' => $idNode,
            ],
        ]);

        $html = $jarboeController->view->showEditForm(false,true);
    }*/


    if ($idNode && $model) {
        $info = $model::find($idNode);
        if (isset($info->template)) {
            $accessTemplateShow =
                config('builder.'.$view->treeName.'.templates.'.$info->template.'.show_templates');

            if (is_array($accessTemplateShow) && count($accessTemplateShow)) {
                $accessTemplateShow = array_flip($accessTemplateShow);

                $templates = array_intersect_key($templates, $accessTemplateShow);
            }
        }
    }

    $view->with('templates', $templates)->with('defaultDefinition', $defaultDefinition);
});

View::composer(['admin::tree.partials.update',
                     'admin::tree.partials.preview',
                     'admin::tree.partials.clone',
                     'admin::tree.partials.revisions',
                     'admin::tree.partials.delete',
                     'admin::tree.partials.constructor',
], function (ViewParam $view) {
    $type = $view->getData()['type'];
    $active = false;
    $caption = '';

    $nodeDefinition = config('builder.'.$view->treeName.'.templates.'
        .$view->item->template.'.node_definition');

    $update = config('builder.tb-definitions.'.$view->treeName.'.'
        .$nodeDefinition.'.actions.'.$type);
    //check present config in template file
    if ($update) {
        $pathToConfig = 'builder.tb-definitions.'.$view->treeName.'.'
            .$nodeDefinition.'.actions.'.$type;
    }

    //check in main file config
    if (! isset($update) || ! $update) {
        $pathToConfig = 'builder.'.$view->treeName.'.actions.'.$type;
        $update = config('builder.'.$view->treeName.'.actions.'.$type);
    }

    if ($update) {
        $caption = config($pathToConfig.'.caption');
        $checkFunction = config($pathToConfig.'.check');
        $active = $checkFunction && $checkFunction();
    }

    $view->with(compact('active', 'caption'));
});
