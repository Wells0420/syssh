<?php
class SS_Controller extends CI_Controller{
	
	public $default_method='lists';

	public $data=array();//传递给模板的参数

	public $main_view_loaded=FALSE;
	public $sidebar_loaded=FALSE;

	public $require_export=true;//页面头尾输出开关（含menu）
	public $require_menu=true;//顶部蓝条/菜单输出开关
	public $as_popup_window=false;
	public $as_controller_default_page=false;
	public $actual_table='';//借用数据表的controller的实际主读写表，如contact为client,query为cases
	
	function __construct(){
		parent::__construct();

		global $class,$method;
		
		//定义$class常量，即控制器的名称
		define('IN_UICE',$class);
		define('METHOD',$method);
		
		$this->config->set_item('timestamp',time());
		$this->config->set_item('microtime',microtime(true));
		$this->config->set_item('date',date('Y-m-d',$this->config->item('timestamp')));
		$this->config->set_item('quarter',date('y',$this->config->item('timestamp')).ceil(date('m',$this->config->item('timestamp'))/3));
	
		db_query("SET NAMES 'UTF8'");
	
		//获得公司信息，见数据库，company表
		if($company_info=company_fetchInfo()){
			foreach($company_info as $config_name => $config_value){
				$this->config->set_item($config_name, $config_value);
			}
		}
	
		//ucenter配置
		if($this->config->item('ucenter')){
			require APPPATH.'helpers/config_ucenter.php';
			require APPPATH.'third_party/client/client.php';
		}

		if($class!='user' && !is_logged(NULL,true)){
			//对于非用户登录/登出界面，检查权限，弹出未登陆（顺便刷新权限）
			redirect('user/login','js',NULL,true);
		}

			
		//使用controller中自定义的默认method
		if($method=='index'){
			$method=$this->default_method;
		}
		
		//根据controller和method请求决定一些参数
		//这相当于集中处理了分散的控制器属性，在团队开发中，这不科学。有空应该把这些设置移动到对应的控制器中
		if(in_array($method,array('add','edit'))){
			$this->as_popup_window=TRUE;
		}
			
		if(in_array($class,array('frame','nav'))){
			$this->require_menu=false;
	
		}elseif($class=='cases'){
			$this->actual_table='case';
			if(($method=='add' || $method=='edit')){
				$this->as_popup_window=FALSE;
				if(is_posted('submit/file_document_list')){
					$this->require_export=false;
				}
				$this->as_popup_window=FALSE;
	
			}elseif($method=='write'){
				$this->require_export=false;
	
			}
		}elseif($class=='client'){
			if($method=='get_source_lawyer'){
				$this->require_export=false;
	
			}elseif($method=='autocomplete'){
				$this->require_export=false;
	
			}
		}elseif($class=='contact'){
			$this->actual_table='client';

		}elseif($class=='cron'){
			ignore_user_abort();
			set_time_limit(0);
			//error_reporting('~E_ALL');
	
			if($method=='script'){
				$this->action='cron_'.$_GET['script'];
	
			}/*else{
				//imperfect uicestone 2012/8/6 定时任务，尚未处理
				$q_cron="SELECT name,cycle,nextrun,lastrun cron where 1=1";
				$r_cron=db_query($q_cron);
				while($cron=mysql_fetch_array($r_cron)){
					if($_G['timestamp'] > $cron['next_run']){
						db_query("UPDATE cron set next_run =".($_G['timestamp']+$cron['cycle'])." WHERE id=".$cron['id']);
					}
				}
			}*/
	
		}elseif($class=='document'){
			if(is_posted('fileSubmit')){
				$this->require_export=false;
	
			}elseif(is_posted('createDirSubmit')){
				$this->require_export=false;
	
			}elseif(is_posted('fav')){
				$this->require_export=false;
	
			}elseif(is_posted('favDelete')){
				$this->require_export=false;
	
			}elseif(($method=='view' || $method=='office_document' || $method=='instrument' || $method=='contact_file' || $method=='rules' || $method=='contract')){//根据目录ID进行定位/文件ID则进行下载
	
				if($method=='office_document'){
					$_GET['view']=869;
	
				}elseif($method=='instrument'){
					$_GET['view']=870;
	
				}elseif($method=='contact_file'){
					$_GET['view']=872;
	
				}elseif($method=='rules'){
					$_GET['view']=874;
	
				}elseif($method=='contract'){
					$_GET['view']=873;
				}
	
	
				option('in_search_mod',false);
	
				$folder=db_fetch_first("SELECT * FROM `document` WHERE id='".intval($_GET['view'])."'");
	
				if($folder['type']!=''){
					$this->action="document_download";
					$this->require_export=false;
				}else{
					$_SESSION[$class]['upID']=$folder['parent'];
					$_SESSION[$class]['currentDir']=$folder['name'];
					$_SESSION[$class]['currentDirID']=$folder['id'];
					$_SESSION[$class]['currentPath']=$folder['path'];
				}
	
			}
		}elseif($class=='evaluation'){
			if($method=='score'){
				$this->as_popup_window=true;
	
			}elseif($method=='score_write'){
				$this->require_export=false;
	
			}
		}elseif($class=='misc'){
			$this->require_export=false;
	
		}elseif($class=='query'){
			$this->actual_table='case';
			$this->as_popup_window=FALSE;
			
	
		}elseif($class=='schedule'){
			if($method=='readcalendar'){
				$this->require_export=false;
	
			}elseif($method=='writecalendar'){
				$this->require_export=false;
	
			}elseif(($method=='list' || $method=='mine' || $method=='plan')){
				if(is_posted('export')){
					$this->require_export=false;
				}
	
			}elseif($method=='listwrite'){//日志列表写入评语/审核时间
				$this->require_export=false;
	
			}
		}elseif($class=='user'){
			if($method=='login'){
				$this->require_menu=false;
	
			}
		}elseif($class=='affair'){
			if($method=='switch'){
				$this->require_export=false;
	
			}
		}elseif($class=='exam'){
			if($method=='save'){
				$_G{'action'}=$class.'_list_save';
				$this->require_export=false;
	
			}
		}elseif($class=='student'){
			$this->as_popup_window=FALSE;
			if($method=='setclass'){
				$this->require_export=false;
	
			}elseif(is_logged('student')){
				post('student/id',$_SESSION['id']);
				$this->as_controller_default_page=true;
	
			}elseif(is_logged('parent')){
				post('student/id',$_SESSION['child']);
				$this->as_controller_default_page=true;
	
			}elseif(is_permitted($class)){//默认action
							}	
		}elseif($class=='survey'){
			if(got('action','homework')){
	
			}
		}elseif($class=='view_score'){
			if(is_posted('export_to_excel')){
				$this->require_export=false;
			}
		}

		if(is_posted('submit/cancel')){
			$this->require_export=false;
			$method='cancel';
		}
	
		$this->load->model('company_model','company');
		
		if(is_file(APPPATH.'models/'.$class.'_model.php')){
			$this->load->model($class.'_model',$class);
		}
	
		if($this->require_export){
			if(IN_UICE=='nav'){
				$this->load->view('head_nav');
			}elseif(IN_UICE=='frame'){
				$this->load->view('head_frame');
			}else{
				$this->load->view('head');
			}
	
			if($this->require_menu){
				$this->load->view('menu');
			}
		}
		
	}
	
	/*
	 * 在每个add页面之前获得数据ID，插入新数据或者根据数据ID获得数据数组
	 */
	function getPostData($id,$callback=NULL,$generate_new_id=true,$db_table=NULL){
		if(isset($id)){
			unset($_SESSION[IN_UICE]['post']);
			post(IN_UICE.'/id',intval($id));
		
		}elseif(is_null(post(IN_UICE.'/id'))){
			unset($_SESSION[IN_UICE]['post']);
		
			$this->processUidTimeInfo(IN_UICE);
		
			if(is_a($callback,'Closure')){
				$CI=&get_instance();
				$callback($CI);
			}
	
			if($generate_new_id){
				if(is_null($db_table)){
					if($this->actual_table!=''){
						$db_table=$this->actual_table;
					}else{
						$db_table=IN_UICE;
					}
				}
				post(IN_UICE.'/id',db_insert($db_table,post(IN_UICE)));
			}
			//如果$generate_new_id==false，那么必须在callback中获得post(IN_UICE/id)
		}
	
		if(!post(IN_UICE.'/id')){
			showMessage('获得信息ID失败','warning');
			exit;
		}
		global $class;
		post(IN_UICE,$this->$class->fetch(post(IN_UICE.'/id')));
	}

	/**
	 * 此方法将被移至SS_Model下
	 */
	function fetchTableArray($query,$field){
		//if($_SESSION['username']=='陆秋石')showMessage($query,'notice');

		$result=db_query($query);

		if($result===false){
			return false;
		}

		$table=array('_field'=>array());

		foreach($field as $k=>$v){
			if(!is_array($v))
				$table['_field'][$k]=$v;
			else{
				$str='';
				if(isset($v['title'])){
					$str=$v['title'];
				}
				if(isset($v['surround_title'])){
					$str=$this->surround($str,$v['surround_title']);
				}elseif(!isset($v['orderby']) || $v['orderby']){
					$str=$this->surround($str,array('mark'=>'a','href'=>"javascript:postOrderby('".$k."')"));
				}
				$table['_field'][$k]['html']=$str;
				if(isset($v['td_title'])){
					$table['_field'][$k]['attrib']=$v['td_title'];
				}
			}
		}

		while($data=db_fetch_array($result)){
			$line_data=array();
			foreach($field as $k => $v){
				if(!is_array($v))
					$line_data[$k]=$this->variableReplace(isset($data[$k])?$data[$k]:NULL,$data);
				else{
					$str=isset($v['content']) ? $v['content'] : (isset($data[$k])?$data[$k]:NULL);
					$str=$this->variableReplace($str,$data);
					if(isset($v['eval']) && $v['eval']){
						$str=eval($str);
					}
					if(isset($v['surround'])){
						array_walk($v['surround'],array($this,'variableReplaceSelf'),$data);
						$str=$this->surround($str,$v['surround']);
					}
					$line_data[$k]['html']=$str;
					if(isset($v['td'])){
						$line_data[$k]['attrib']=$this->variableReplace($v['td'],$data);
					}
				}
			}
			$table[]=$line_data;
		}

		return $table;
	}

	/*
	 * 历史遗留写法，因为及其简化，保留至今
	 */
	function exportTable($q_data,$field,$menu=NULL,$surroundForm=false,$surroundBox=true,array $attributes=array(),$show_line_id=false,$trim_columns=false){
		$array=fetchTableArray($q_data,$field);
		$this->arrayExportTable($array,$menu,$surroundForm,$surroundBox,$attributes,$show_line_id,$trim_columns);
	}


	/*
	 * 仅用在fetchTableArray中
	 * 将field->content等值中包含的变量占位替换为数据结果中他们的值
	 */
	function variableReplace($content,$data){
		while(preg_match('/{(\S*?)}/',$content,$match)){
			if(!isset($data[$match[1]])){
				$data[$match[1]]=NULL;
			}
			$content=str_replace($match[0],$data[$match[1]],$content);
		}
		return $content;
	}

	function variableReplaceSelf(&$content,$key,$data){
		$content=$this->variableReplace($content,$data);
	}

	/*
	 * 包围，生成html标签的时候很有用
	 * $surround=array(
	 * 		'mark'=>'div',
	 * 		'attrib1'=>'value1',
	 * 		'attrib2'=>'value2'
	 * );
	 * 将生成<div attrib1="value1" attrib2="value2">$str</div>
	 */
	function surround($str,$surround){
		if($str=='')
			return '';

		$mark=$surround['mark'];
		unset($surround['mark']);
		$property=db_implode($surround,' ',NULL,'=','"','"','','value',false);
		return '<'.$mark.' '.$property.'>'.$str.'</'.$mark.'>';

	}

	/*controller/*_list.php类控制单元中用到的处理查询语句并返回相关界面组件的函数集*/
	/*
	 * 处理查询语句，添加搜索条件，返回一个搜索表单，配合view/*_*_sidebar.htm使用
	 */
	function processSearch(&$q,$fields){
		if(is_posted('search_cancel')){
			unset($_SESSION[IN_UICE][METHOD]['in_search_mod']);
			unset($_SESSION[IN_UICE][METHOD]['keyword']);
		}

		if(is_posted('search')){
			option('keyword',array_trim($_POST['keyword']));
			option('in_search_mod',true);
		}

		if(option('in_search_mod')){

			$condition_search='';

			foreach(option('keyword') as $field => $keywords){

				$condition=preg_split('/[\s]+|,/',option('keyword/'.$field));

				$condition=' AND ('.db_implode($condition,' AND ',db_field_name($field),' LIKE ',"'%","%'",'').')';

				$condition_search.=$condition;

			}
			$q.=$condition_search;
		}

		$search_bar='<form method="post" name="search">'.
			'<table class="contentTable search-bar" cellpadding="0" cellspacing="0" align="center">'.
				'<thead><tr><td width="80px">搜索</td><td>&nbsp;</td></tr></thead>'.
				'<tbody>';
		foreach($fields as $field_table_name => $field_ui_name){
			$search_bar.='<tr><td>'.
				'<label>'.$field_ui_name.'：'.'</label></td>'.
				'<td>'.
				'<input type="text" name="keyword['.$field_table_name.']" value="'.option('keyword/'.$field_table_name).'" /><br />'.
				'</td></tr>';
		}

		$search_bar.='<tr><td colspan="2"><input type="submit" name="search" value="搜索" tabindex="0" />';
		if(option('in_search_mod')){
			$search_bar.='<input type="submit" name="search_cancel" value="取消" tabindex="1" />';
		}
		$search_bar.='</td></tr></tbody>'.
				'</table>'.
			'</form>';

		return $search_bar;
	}

	/*
	 * 为sql语句添加排序依据，无反回值
	 */
	function processOrderby(&$q,$defaultOrder,$defaultMethod=NULL,$field_need_convert=array(),$only_table_of_the_page=true){
		if (is_null(option('orderby'))){
			option('orderby',$defaultOrder);
		}
		if (is_null(option('method'))){
			option('method',is_null($defaultMethod)?'ASC':$defaultMethod);
		}

		if($only_table_of_the_page && is_posted('orderby') && !is_null(option('orderby')) && $_POST['orderby']==$_SESSION[IN_UICE][METHOD]['orderby']){
			if(option('method')=='ASC'){
				option('method','DESC');
			}else{
				option('method','ASC');
			}
		}

		if(is_posted('orderby')){
			option('orderby',$_POST['orderby']);
		}
		if(is_posted('method')){
			option('method',$_POST['method']);
		}

		$needConvert=in_array(option('orderby'),$field_need_convert);

		$q.= ' ORDER BY '.
			($needConvert?'convert(':'').
			db_field_name(option('orderby')).
			($needConvert?' USING GBK) ':' ').
			option('method');
	}

	/*
	 * 为查询语句加上日期条件
	 */
	function dateRange(&$q,$date_field,$date_field_is_timestamp=true){
		if(is_posted('date_range_cancel')){
			unset($_SESSION[IN_UICE][METHOD]['in_date_range']);
			unset($_SESSION[IN_UICE][METHOD]['date_range']);
		}

		if(is_posted('date_range')){
			if(!strtotime($_POST['date_from']) || !strtotime($_POST['date_to'])){
				showMessage('日期格式错误','warning');

			}else{
				option('date_range/from_timestamp',strtotime($_POST['date_from']));
				option('date_range/to_timestamp',strtotime($_POST['date_to'])+86400);

				option('date_range/from',date('Y-m-d',option('date_range/from_timestamp')));
				option('date_range/to',date('Y-m-d',option('date_range/to_timestamp')-86400));

				option('in_date_range',true);
			}
		}

		if(option('in_date_range')){

			if($date_field_is_timestamp){
			$condition_date_range=" AND (".db_field_name($date_field).">='".option('date_range/from_timestamp')."' AND ".db_field_name($date_field)."<'".option('date_range/to_timestamp')."')";
			}else{
				$condition_date_range=" AND (".db_field_name($date_field).">='".option('date_range/from')."' AND ".db_field_name($date_field)."<='".option('date_range/to')."')";
			}

			$q.=$condition_date_range;
		}

		$date_range_bar=
		'<form method="post" name="date_range">'.
			'<table class="contentTable search-bar" cellpadding="0" cellspacing="0" align="center">'.
			'<thead><tr><td width="60px">日期</td><td>&nbsp;</td></tr></thead>'.
			'<tbody>'.
			'<tr><td>开始：</td><td><input type="text" name="date_from" value="'.option('date_range/from').'" class="date" /></td></tr>'.
			'<tr><td>结束：</td><td><input type="text" name="date_to" value="'.option('date_range/to').'" class="date" /></td></tr>'.
			'<input style="display:none;" name="date_field" value="'.$date_field.'" />';

		$date_range_bar.='<tr><td colspan="2"><input type="submit" name="date_range" value="提交" />';
		if(option('in_date_range')){
			$date_range_bar.='<input type="submit" name="date_range_cancel" value="取消" tabindex="1" />';
		}
		$date_range_bar.='</td></tr></tbody></table></form>';

		return $date_range_bar;
	}

	/*
	 * TODO 添加addCondition()的描述
	 */
	function addCondition(&$q,$condition_array,$unset=array()){
		foreach($unset as $changed_variable => $unset_variable){
			if(is_posted($changed_variable)){
				unset($_SESSION[IN_UICE][METHOD][$unset_variable]);
			}
		}

		foreach($condition_array as $variable=>$field){
			if(is_posted($variable)){
				option($variable,$_POST[$variable]);
			}

			if(!is_null(option($variable)) && option($variable)!=''){
				$q.=' AND '.db_field_name($field)."='".option($variable)."'";
			}
		}
		return $q;
	}

	/*
	 * 为sql语句添加LIMIT字段，达到分页目的
	 */
	function processMultiPage(&$q,$q_rows=NULL){
		if(is_null($q_rows)){
			$q_rows=$q;
			if(preg_match('/GROUP BY[^()]*?[ORDER BY].*?$/',$q_rows)){
				$q_rows="SELECT COUNT(*) AS number FROM (".$q_rows.")query";
			}else{
				$q_rows=preg_replace('/^[\s\S]*?FROM /','SELECT COUNT(1) AS number FROM ',$q_rows);
				$q_rows=preg_replace('/GROUP BY(?![\s\S]*?WHERE)[\s\S]*?$/','',$q_rows);
				$q_rows=preg_replace('/ORDER BY(?![\s\S]*?WHERE)[\s\S]*?$/','',$q_rows);
			}
		}

		$rows=db_fetch_field($q_rows);

		if(option('list/start')>$rows || $rows==0){
			//已越界或空列表时，列表起点归零
			option('list/start',0);

		}elseif(option('list/start')+option('list/item')>=$rows && $rows>option('list/items')){
			//末页且非唯一页时，列表起点定位末页起点
			option('list/start',$rows - ($rows % option('list/items')));
		}

		if(!is_null(option('list/start')) && option('list/items')){
			if(is_posted('previousPage')){
				option('list/start',option('list/start')-option('list/items'));
				if(option('list/start')<0){
					option('list/start',0);
				}
			}elseif(is_posted('nextPage')){
				if(option('list/start')+option('list/items')<$rows){
					option('list/start',option('list/start')+option('list/items'));
				}
			}elseif(is_posted('firstPage')){
				option('list/start',0);
			}elseif(is_posted('finalPage')){
				if($rows % option('list/items')==0){
					option('list/start',$rows - option('list/items'));
				}else{
					option('list/start',$rows - ($rows % option('list/items')));
				}
			}
		}else{
			option('list/start',0);
			option('list/items',25);
		}

		$q.=" LIMIT ".option('list/start').",".option('list/items');

		$listLocator=($rows==0?0:option('list/start')+1)."-".
		(option('list/start')+option('list/items')<$rows?(option('list/start')+option('list/items')):$rows).'/'.$rows;

		$listLocator.=
			'<button type="button" class="nav" onclick="post(\'firstPage\',true)"'.(option('list/start')==0?' disabled="disabled"':'').'>&lt;&lt;</button>'.
			'<button type="button" class="nav" onclick="post(\'previousPage\',true)"'.(option('list/start')==0?' disabled="disabled"':'').'>&nbsp;&lt;&nbsp;</button>'.
			'<button type="button" class="nav" onclick="post(\'nextPage\',true)"'.(option('list/start')+option('list/items')>=$rows?' disabled="disabled"':'').'>&nbsp;&gt;&nbsp;</button>'.
			'<button type="button" class="nav" onclick="post(\'finalPage\',true)"'.(option('list/start')+option('list/items')>=$rows?' disabled="disabled"':'').'>&gt;&gt;</button>';
		return $listLocator;
	}

	/* 
	 * $extra_action 是一个数组，接受除了返回列表/关闭窗口之外的其他提交后动作
	 * $after_update为数据库更新成功后，跳转前需要的额外操作
	 */
	function processSubmit($submitable,$after_update=NULL,$update_table=NULL,$set_display=true,$set_time=true,$set_user=true){
		if($set_display){
			post(IN_UICE.'/display',1);
		}

		if($set_time){
			post(IN_UICE.'/time',$this->config->item('timestamp'));
		}

		if($set_user){
			post(IN_UICE.'/uid',$_SESSION['id']);
			post(IN_UICE.'/username',$_SESSION['username']);
		}

		post(IN_UICE.'/company',$this->config->item('company'));

		if(is_null($update_table)){
			if($this->actual_table!=''){
				$update_table=$this->actual_table;
			}else{
				$update_table=IN_UICE;
			}
		}

		if($submitable){
			if(db_update($update_table,post(IN_UICE),"id='".post(IN_UICE.'/id')."'")){

				if(is_a($after_update,'Closure')){
					$after_update();
				}

				if(is_posted('submit/'.IN_UICE)){

					if(!$this->config->item('as_controller_default_page')){
						unset($_SESSION[IN_UICE]['post']);
					}

					if($this->config->item('as_popup_window')){
						refreshParentContentFrame();
						closeWindow();
					}else{
						if($this->config->item('as_controller_default_page')){
							showMessage('保存成功~');
						}else{
							redirect((sessioned('last_list_action')?$_SESSION['last_list_action']:IN_UICE));
						}
					}
				}
			}
		}
	}

	function processUidTimeInfo($affair){
		if(!post($affair)){
			post($affair,array());
		}
		post($affair,post($affair)+uidTime());
	}
	
	function cancel(){
		unset($_SESSION[IN_UICE]['post']);
		
		db_delete($this->actual_table==''?IN_UICE:$this->actual_table,"uid='".$_SESSION['id']."' AND display=0");//删除本用户的误添加数据
		
		if($this->as_popup_window){
			closeWindow();
		}else{
			redirect((sessioned('last_list_action')?$_SESSION['last_list_action']:IN_UICE));
		}
	}

}
?>