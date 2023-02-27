<?
(new categories_list($catId))->run();

class categories_list{

    private $db;

    private $catId;

    private $depth;

    private $data;

    private $templates;


    /**
     * уровни глубины в иерархии категорий
     * @var array
     */
    private $levels = [
        3 => 'brand',
        4 => 'model',
        5 => 'category',
        6 => 'subcategory'
    ];

    public function __construct($catId)
    {
        $this->db = DB::getInstance()->connect();
        $this->catId = intval($catId);
        $this->templates['root'] = template::instance()->tfile($_SERVER['DOCUMENT_ROOT'].'/templates/snippets/categories/root.tpl');
        $this->templates['column'] = template::instance()->tfile($_SERVER['DOCUMENT_ROOT'].'/templates/snippets/categories/column.tpl', true);
        $this->templates['item'] = template::instance()->tfile($_SERVER['DOCUMENT_ROOT'].'/templates/snippets/categories/item.tpl', true);
    }


    public function run(){
        try{
            $this->checkCatId();
            $this->getURIDepth();
            $this->getData();
            $this->render();
        } catch (Exception $e){
        }
    }

    private function getModelId(){
        $segments = $this->getURIDepth();
        $modelURL = $this->db->real_escape_string($segments[3]);

        return $this->db->query("SELECT cat_id FROM market_categories WHERE cat_url = '{$modelURL}'")->fetch_object()->cat_id;
    }


    /**
     * вытягивает данные на всех возможных уровнях и отдает их в стандартизированном виде
     * @return bool
     */
    private function getData(){
        $depthLevel = $this->levels[$this->depth];
        if(in_array($depthLevel, [
            'model',
            'category'
        ])) {
            $modelId = $this->getModelId();
        }

        $data = [];
        switch ($depthLevel){
            case 'brand':
                $data = $this->getModelsData();
                break;
            case 'model':
                $data = $this->getCategoryData($modelId);
                break;
            case 'category':
                $data = $this->getSubcategoryData($modelId);
                break;
            case 'subcategory':
                $data = [];
                break;
        }

        $this->data = $data;

        return true;
    }

    private function getModelsData(){
        $categoryId = intval($this->catId);
        $data = [];
        $q = $this->db->query("SELECT cat_name, CAT_ABSOLUTE_URL(cat_id) as absolute_url FROM market_categories WHERE cat_pid = {$categoryId} AND cat_publish = 1 AND cat_goods_recursive > 0");

        while($row = $q->fetch_assoc()){
            $data[] = $row;
        }

        return $data;
    }

    private function getCategoryData($modelId){
        $modelId = intval($modelId);
        $baseURI = explode('?', $_SERVER['REQUEST_URI'])[0];
        $data = [];
        $sql = "SELECT cat_name, cat_url FROM market_subcategories WHERE cat_id IN (SELECT DISTINCT cat_pid FROM market_subcategories WHERE cat_id IN (SELECT good_subcat_id FROM market_goods WHERE good_cid = {$modelId} AND good_subcat_id IN (SELECT cat_id FROM market_subcategories WHERE cat_pid IS NOT NULL)))";
        $q = $this->db->query($sql);
        while($row = $q->fetch_assoc()){
            $catURI = $baseURI.$row['cat_url'].'/';
            $temp = [
                'cat_name' => $row['cat_name'],
                'absolute_url' => $catURI,
            ];
            $data[] = $temp;
        }

        return $data;
    }

    private function getSubcategoryData($modelId){
        $categoryId = intval($this->catId);
        $modelId = intval($modelId);
        $data = [];
        $baseURI = explode('?', $_SERVER['REQUEST_URI'])[0];

        $sql = "SELECT cat_name, cat_url, (SELECT cat_url FROM market_subcategories as sq WHERE sq.cat_id = (SELECT cat_pid FROM market_subcategories as ssq WHERE ssq.cat_id = root.cat_id)) as parent_cat_url  FROM market_subcategories as root WHERE cat_id IN (SELECT DISTINCT good_subcat_id FROM market_goods WHERE good_cid = {$modelId} AND good_subcat_id IN (SELECT cat_id FROM market_subcategories WHERE cat_pid = {$categoryId}))";

        $q = $this->db->query($sql);

        while($row = $q->fetch_assoc()){
            $catURI = $baseURI.$row['parent_cat_url'].'/'.$row['cat_url'].'/';
            $temp = [
                'cat_name' => $row['cat_name'],
                'absolute_url' => $catURI,
            ];
            $data[] = $temp;
        }

        return $data;
    }


    /**
     * возвращает текущую глубину в иерархии категорий
     * @return array
     */
    private function getURIDepth(){
        $segments = explode('/', trim(explode('?', $_SERVER['REQUEST_URI'])[0], '/'));

        $this->depth = count($segments);

        return $segments;
    }



    private function checkCatId(){
        if(empty($this->catId)){

            throw new Exception('empty cat ID');
        }
    }

    private function render($success = true){
        if($success){
            $col = '';
            $columns = '';
            $i = 0;
            $total = count($this->data);
            $perColumn = ceil($total /3 );
            foreach($this->data as $item){
                $col .= $this->templates['item']->temp([
                    'url' => $item['absolute_url'],
                    'name' => $item['cat_name']
                ])->template;
                $i++;
                if($i == $perColumn){
                    $i = 0;
                    $columns .= $this->templates['column']->temp([
                        'items' => $col
                    ])->template;
                    $col = '';
                }
            }
            if(!empty($col)){
                $columns .= $this->templates['column']->temp([
                    'items' => $col
                ])->template;
            }

            echo $this->templates['root']->temp([
                'columns' => $columns
            ])->template;
        }
    }

}













return 1;
$db = DB::getInstance()->connect();
function getParentCatsFull($db, array $cats_arr = array(), $sub = '')
{
    $sub = $db->real_escape_string($sub);
    $cats = $parent_cats = array();
    $cid = null;
    $stmt['cat_query'] = $db->prepare("SELECT cat_id, cat_pid FROM `market_{$sub}categories` WHERE cat_id = ? LIMIT 1");

    foreach ($cats_arr as $cat) {
        $cat_pid = $cat;
        if (!in_array($cat, $cats)) {
            while (!is_null($cat_pid)) {

                $stmt['cat_query']->bind_param('i', $cat_pid);
                $stmt['cat_query']->execute();
                $stmt['cat_query']->bind_result($cid, $cat_pid);
                $stmt['cat_query']->fetch();

                if (!in_array($cid, $cats)) {
                    $cats[] = $cid;
                }
                if (!in_array($cid, $parent_cats) && is_null($cat_pid)) {
                    $parent_cats[] = $cid;
                }
                $stmt['cat_query']->free_result();
            }
        }
    }

    return $cats;
}

//возвращает url категории по id
function catUrl($cat_id, $sub = ''){
    $db = DB::getInstance()->connect();
    $sub = $db->real_escape_string($sub);
    $cat_array =array($cat_id);
    $Fcats = getParentCatsFull($db, $cat_array, $sub);

    $Fcats = implode("," , $Fcats);

    $parts_param= array();

    if ($Fcats) {
        $queryCatName = $db->query("SELECT cat_url FROM `market_{$sub}categories` WHERE cat_id IN ($Fcats) ORDER BY FIELD(cat_id,$Fcats) ")or die($db->error);
    } else {
        $queryCatName = $db->query("SELECT cat_url FROM `market_{$sub}categories` WHERE cat_id IS NULL ORDER BY cat_id ")or die($db->error);
    }
    if($queryCatName->num_rows){
        while($result = $queryCatName->fetch_object()){
            array_push($parts_param,$result->cat_url);
        }
    }

    $parts_param = array_reverse($parts_param);

    foreach($parts_param as $par){
        $cat_url .= $par .'/';
    }

    return $cat_url;
}


if ($_SERVER['REQUEST_URI'] == '/market/') {
    $query = $db->query('
                            SELECT `cat_id`, `cat_name`, `cat_url`, `cat_sort` FROM `market_categories`
                            WHERE `cat_pid` IS NULL AND `cat_publish` = 1
                            ORDER BY `cat_sort` ASC, `cat_name` ASC
') or die ($db->error);
} else {
    $query = $db->query('
                            SELECT `cat_id`, `cat_name`, `cat_url`, `cat_sort` FROM `market_categories`
                            WHERE `cat_pid` = ' . intval($catId) . ' AND `cat_publish` = 1 AND cat_goods_recursive > 0
                            ORDER BY `cat_name` ASC
') or die ($db->error);
}
//xdump($query);
if ($query->num_rows > 0) {
    $categories = '';
    $categories .= '<div class="mpn_wrap">';
    $categories .= '<ul class="subCatsWithImages">';
    while ($result = $query->fetch_object()) {
        $cat_id = $result->cat_id;
        $cat_url = catUrl($cat_id);
        $cat_name = $result->cat_name;

        $categories .= '<li>';
        $categories .= '<span class="dspl_ib">';
        if ($_SERVER['REQUEST_URI'] != '/market/') {
            // $query_par_cat = $db->query('
            //                                     SELECT `cat_id`, `cat_url` FROM `market_categories`
            //                                     WHERE `cat_id` = ' . intval($catId) . '
            //                                     ') or die ($db->error);
            // while ($value = $query_par_cat->fetch_object()) {
            //     $par_cat_url = $value->cat_url;
            // }
            $categories .= '<a href="/market/' .  $cat_url . '">';
        } else {
            $categories .= '<a href="/market/' . $cat_url . '/">';
        }
        //  $categories .= '<img src="/pictures/backgroundtransparent/width32/height32/cat_id' . $cat_id . '.jpg">';
        $categories .= '</a>';
        $categories .= '</span>';
        $categories .= '<span class="dspl_ib" style="width: 200px; margin-left: 8px;">';
        $categories .= '<a href="/market/'  . $cat_url . '" class="fs-15 c-black">' . $cat_name . '</a>	';
        $categories .= '</span>';
        $categories .= '</li>';
    }
    // $categories .= '<br class="a-clear-b">';
    $categories .= '</ul>';
    $categories .= '</div>';
} else {

    if(stripos($_SERVER['REQUEST_URI'],'b_u_zapchasti') !== false) { // Только в разделе Б/У

        // >>> // maks: здесь просходит вывод подкатегорий из market_subcategories >>>
        /* SUBCATS */


        function checkRootSubcats($db, $stdCat = false)
        { // проверяет категории, выбирает только те в которых есть товары
            if ($stdCat != false) {
                $stdCat = intval($stdCat);
//                xdump("SELECT DISTINCT cat_id FROM `market_subcategories` ms
//                 JOIN `market_goods` mg ON mg.`good_subcat_id` = ms.cat_id
//                 WHERE mg.good_cid = {$stdCat} OR good_id IN (SELECT copy_gid FROM market_goods_copy WHERE copy_cid = {$stdCat})");

                $q = $db->query("SELECT good_subcat_id FROM market_goods
                                WHERE good_cid = {$stdCat}
                            UNION 
                            SELECT good_subcat_id FROM market_goods
                                WHERE good_id IN (SELECT copy_gid FROM market_goods_copy
                                                    WHERE copy_cid = {$stdCat}
                                                )");
            } else {

                $q = $db->query("SELECT DISTINCT cat_id FROM `market_subcategories` ms
                 JOIN `market_goods` mg ON mg.`good_subcat_id` = ms.cat_id");
            }

            if($q->num_rows == 0) return 0;

            while ($l = $q->fetch_object()) {
                $array[] = $l->good_subcat_id;
            }

            //  $resultArr = array_unique($array);
            $resultArr = $array;
            $result = implode(', ', $resultArr);

            $q2 = $db->query("SELECT DISTINCT cat_pid FROM `market_subcategories`
                             WHERE cat_id IN(" . $result . ")");

            if($q2->num_rows == 0) return 0;


            while ($l2 = $q2->fetch_object()) {
                $array2[] = intval($l2->cat_pid);
                //пофиксена ошибка - без интвал тут мог быть нулл и потом возникала ошибка в скул запросе
            }
            //    $result2 = array_unique($array2);
            $result2 = $array2;
            $result2 = array_merge($result2, $resultArr);
            $result2 = implode(', ', $result2);
            return $result2;
        }

        function getCatId($db)
        { // Возвращает ID классической категории, в которой лежит товар
            $exp = explode('/', $_SERVER['REQUEST_URI']);
            $urlCat = $db->real_escape_string($exp[4]);

            $q = $db->query("SELECT cat_id FROM `market_categories`
                         WHERE cat_url = '" . $urlCat . "'");
            if ($q->num_rows == 0) return false;
            return $q->fetch_object()->cat_id;
        }

        $subIds = checkRootSubcats($db, getCatId($db));

        $isNull = false;
        $reqUri = $_SERVER['REQUEST_URI'];
        $reqUriArr = array_filter(explode('/', $reqUri));

        $cat_url = end($reqUriArr);
        $showRootq = $db->query("
        SELECT `cat_id` FROM `market_subcategories`
        WHERE `cat_url` = '{$cat_url}'
    ") or die ($db->error);

        if (!$showRootq->num_rows) {


            $query = $db->query('
            SELECT `cat_id`, `cat_name`, `cat_url`, `cat_sort` FROM `market_subcategories`
            WHERE `cat_pid` IS NULL AND `cat_publish` = 1 AND cat_id IN (' . $subIds . ')
            ORDER BY `cat_name` ASC, `cat_sort` ASC
        ') or die ($db->error);
        } else {


            $query = $db->query('
            SELECT `cat_id`, `cat_name`, `cat_url`, `cat_sort` FROM `market_subcategories`
            WHERE `cat_pid` = ' . intval($catId) . ' AND `cat_publish` = 1 AND cat_id IN (' . $subIds . ')
            ORDER BY `cat_name` ASC, `cat_sort` ASC
        ') or die ($db->error);

        }

        $categories = '';
        $categories .= '<div class="mb-15">';
        $categories .= '<ul class="subCatsWithImages mb-15">';
        while ($result = $query->fetch_object()) {

            $cat_id = $result->cat_id;
            $cat_url = catUrl($cat_id, 'sub');
            $cat_name = $result->cat_name;

            $reqUriArr = explode('/', $reqUri);
            $cat_urlArr = explode('/', $cat_url);

            $uriArr = array_values(array_unique(array_filter(array_merge($reqUriArr, $cat_urlArr))));

            // убираем get-параметры, чтобы не было ссылок в виде: /market/kia/?limit=12/dvigatel
            foreach ($uriArr as $key => $uri) {
                if($uri[0] == '?') unset($uriArr[$key]);
                if(preg_match('/limit-\d+/', $uri, $matches)) {
                    unset($uriArr[$key]);
                }
                if(preg_match('/page-\d+/', $uri, $matches)){
                    unset($uriArr[$key]);
                }
            }
            if(count($uriArr) == 7){
                $uriArr = array_values($uriArr);
                $uriArr[4] = array_pop($uriArr);
                array_pop($uriArr);
            }

            $uriStr = '/' . implode('/', $uriArr) . '/';

            $dump = var_export($parts_param, TRUE);
            $fp = fopen('log.txt', 'a');
            fwrite($fp, 'par_cats=>' . $dump . PHP_EOL);
            fclose($fp);

            $categories .= '<li>';
            $categories .= '<span class="dspl_ib">';

            if ($_SERVER['REQUEST_URI'] !== '/market/') {
                // $query_par_cat = $db->query('
                //                                     SELECT `cat_id`, `cat_url` FROM `market_categories`
                //                                     WHERE `cat_id` = ' . intval($catId) . '
                //                                     ') or die ($db->error);
                // while ($value = $query_par_cat->fetch_object()) {
                //     $par_cat_url = $value->cat_url;
                // }
                $categories .= '<a href="' . $uriStr . '">';
            } else {
                $categories .= '<a href="' . $uriStr . '/">';
            }
            // $categories .= '<img src="/pictures/backgroundtransparent/width32/height32/cat_id' . $cat_id . '.jpg">';

//        $categories .= '<img src="/pictures/backgroundtransparent/width32/height32/cat_id1878541.jpg">';
            $categories .= '</a>';
            $categories .= '</span>';
            $categories .= '<span class="dspl_ib" style="width: 200px; margin-left: 8px;">';
            $categories .= '<a href="' . $uriStr . '" class="fs-15 c-black">' . $cat_name . '</a> ';
            $categories .= '</span>';
            $categories .= '</li>';
        }
        $categories .= '<br class="a-clear-b">';
        $categories .= '</ul>';
        $categories .= '</div>';

        // maks <<<

    } // Только в разделе Б/У
}
echo $categories;
/*426*/?>
