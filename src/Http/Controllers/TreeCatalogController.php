<?php

namespace Vis\Builder;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Response;

/**
 * Class TreeCatalogController.
 */
class TreeCatalogController
{
    /**
     * @var
     */
    protected $model;
    /**
     * @var array
     */
    protected $options;
    /**
     * @var
     */
    protected $nameTree;
    /**
     * @var JarboeController
     */
    protected $controller;

    /**
     * TreeCatalogController constructor.
     * @param $model
     * @param array $options
     * @param $nameTree
     */
    public function __construct($model, array $options, $nameTree)
    {
        $this->model = $model;
        $this->options = $options;
        $this->nameTree = $nameTree;

        $this->controller = new JarboeController($options);
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options = [])
    {
        $this->options = $options;
    }

    // end setOptions

    /**
     * @return array|\Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View|void
     */
    public function handle()
    {
        switch (request('query_type')) {
            case 'do_create_node':
                return $this->doCreateNode();

            case 'clone_record_tree':
                return $this->doCloneRecord();

            case 'do_change_active_status':
                return $this->doChangeActiveStatus();

            case 'do_change_position':
                return $this->doChangePosition();

            case 'do_delete_node':
                return $this->doDeleteNode();

            case 'do_edit_node':
                return $this->doEditNode();

            case 'do_update_node':
                return $this->doUpdateNode();

            case 'get_edit_modal_form':
                return $this->getEditModalForm();

            case 'get_create_modal_form':
                return $this->getCreateModalForm();

            default:
                return $this->handleShowCatalog();
        }
    }

    // end handle

    public function doUpdateNode()
    {
        $node = $this->model::find(request('pk'));
        $node->template = request('value');
        $node->save();

        $node->clearCache();
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function doCreateNode()
    {
        $model = $this->model;

        $root = $model::find(request('node', 1));

        $node = new $model();

        $node->parent_id = request('node', 1);
        $node->title = request('title');
        $node->template = request('template') ?: '';
        $node->slug = request('slug') ?: request('title');
        $node->is_active = 1;
        $node->save();

        $node->checkUnicUrl();

        $root->children()->count() == 1 ? $node->makeChildOf($root) : $node->makeFirstChildOf($root);

        $root->clearCache();

        return Response::json([
            'status' => true,
        ]);
    }

    /**
     * @return array
     */
    public function doCloneRecord()
    {
        $model = $this->model;
        $root = $model::find(request('node', 1));
        $id = request('id');

        $this->cloneRecursively($id);

        $root->clearCache();

        return [
            'id' => $id,
        ];
    }

    /**
     * @param $id
     * @param string $parentId
     */
    private function cloneRecursively($id, $parentId = '')
    {
        $model = $this->model;

        $page = $model::where('id', $id)->select('*')->first()->toArray();
        $idClonePage = $page['id'];
        unset($page['id']);
        if ($parentId) {
            $page['parent_id'] = $parentId;
        }

        if ($page['parent_id']) {
            $root = $model::find($page['parent_id']);

            $rec = new $model();
            $countPages = $model::where('parent_id', $page['parent_id'])->where('slug', $page['slug'])->count();

            if ($countPages) {
                $page['slug'] = $parentId ?
                                        $page['slug'].'_'.$page['parent_id'] :
                                        $page['slug'].'_'.time();
            }

            foreach ($page as $k => $val) {
                $rec->$k = $val;
            }

            $rec->save();
            $lastId = $rec->id;

            $rec->makeChildOf($root);
        }

        $folderCheck = $model::where('parent_id', $idClonePage)->select('*')->orderBy('lft', 'desc')->get()->toArray();
        if (count($folderCheck)) {
            foreach ($folderCheck as $pageChild) {
                $this->cloneRecursively($pageChild['id'], $lastId);
            }
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function doChangeActiveStatus()
    {
        $node = $this->model::find(request('id'));
        $node->is_active = request('is_active') ? '1' : '0';

        $node->save();

        $node->clearCache();

        return Response::json([
            'active' => true,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function doChangePosition()
    {
        $id = request('id');
        $idParent = request('parent_id', 1);
        $idLeftSibling = request('left_sibling_id');
        $idRightSibling = request('right_sibling_id');

        $item = $this->model::find($id);
        $root = $this->model::find($idParent);

        $prevParentID = $item->parent_id;
        $item->makeChildOf($root);

        $item->save();

        if ($prevParentID == $idParent) {
            if ($idLeftSibling) {
                $item->moveToRightOf($this->model::find($idLeftSibling));
            } elseif ($idRightSibling) {
                $item->moveToLeftOf($this->model::find($idRightSibling));
            }
        }

        $root->clearCache();

        $item = $this->model::find($item->id);
        $item->checkUnicUrl();

        $data = [
            'status' => true,
            'item' => $item,
            'parent_id' => $root->id,
        ];

        return Response::json($data);
    }

    /**
     * @return mixed
     */
    public function process()
    {
        $idNode = request('page_id', request('node', 1));
        $current = $this->model::find($idNode);

        $templates = config('builder.'.$this->nameTree.'.templates');
        $template = config('builder.'.$this->nameTree.'.default');
        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        return \Jarboe::table([
            'url'      => URL::current(),
            'def_name' => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function doDeleteNode()
    {
        $this->model::destroy(request('id'));

        $modelObj = $this->model::find('1');
        $modelObj->clearCache();

        return Response::json([
            'status' => true,
        ]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function handleShowCatalog()
    {
        $parentIDs = [];
        $treeName = $this->nameTree;
        $controller = $this->controller;

        $perPage = Session::get('table_builder.'.$treeName.'.node.per_page', 20);

        $idNode = request('node', 1);
        $current = $this->model::find($idNode);

        foreach ($current->getAncestors() as $anc) {
            $parentIDs[] = $anc->id;
        }

        $children = $current->children();

        //filter ids
        $actions = config('builder.'.$treeName.'.actions.show');

        if ($actions && $actions['check']() !== true && is_array($actions['check']())) {
            $arrIdsShow = $actions['check']();

            foreach ($actions['check']() as $id) {
                $arrIdsShow[] = $this->model::find($id)->getDescendants()->pluck('id')->toArray();
            }

            $arrIdsShow = array_flatten($arrIdsShow);
            $children = $children->whereIn('id', $arrIdsShow);
        }
        //filter ids end

        $children = $children->paginate($perPage);

        $templates = config('builder.'.$treeName.'.templates');
        $template = config('builder.'.$treeName.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $content = view('admin::tree.content',
            compact('current', 'template', 'treeName', 'children', 'controller', 'perPage'));
        $treeView = Request::ajax() ? 'tree_ajax' : 'tree';

        return view('admin::'.$treeView,
            compact('content', 'current', 'parentIDs', 'treeName', 'controller', 'perPage'));
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEditModalForm()
    {
        $idNode = request('id');

        $current = $this->model::find($idNode);

        $templates = config('builder.'.$this->nameTree.'.templates');
        $template = config('builder.'.$this->nameTree.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $jarboeController = new JarboeController([
            'url'      => URL::current(),
            'def_name' => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ]);

        $html = $jarboeController->view->showEditForm($idNode, true);

        return Response::json([
            'status' => true,
            'html' => $html,
        ]);
    }

    public function getCreateModalForm()
    {
        $idNode = request('id');

        $defaultDefinition = config('builder.'.$this->nameTree.'.default_definition');

        $jarboeController = new JarboeController([
            'url'      => URL::current(),
            'def_name' => $this->nameTree.'.'.$defaultDefinition,
            'additional' => [
                'node'    => $idNode,
                //'current' => $idNode,
            ],
        ]);

        $html = $jarboeController->view->showEditForm(false, true);

        return Response::json([
            'status' => true,
            'html' => $html,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function doEditNode()
    {
        $idNode = request('id');
        $current = $this->model::find($idNode);

        $templates = config('builder.'.$this->nameTree.'.templates');
        $template = config('builder.'.$this->nameTree.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $jarboeController = new JarboeController([
            'url'        => URL::current(),
            'def_name'   => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ]);

        $result = $jarboeController->query->updateRow(Input::all());

        $item = $this->model::find($idNode);
        $item->clearCache();
        $item->checkUnicUrl();
        $treeName = $this->nameTree;
        $result['html'] = view('admin::tree.content_row',
            compact('item', 'treeName', 'controller'))->render();

        return Response::json($result);
    }
}
