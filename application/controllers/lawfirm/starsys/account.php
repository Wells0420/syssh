<?php
class Account extends SS_controller{
	
	var $list_args;
	
	var $search_items=array();
	
	function __construct(){
		
		parent::__construct();
		$this->load->model('account_model','account');
		
		$this->list_args=array(
			'account'=>array('heading'=>'账目编号'),
			'project_name'=>array('heading'=>array('data'=>'项目','width'=>'30%'),'cell'=>array('class'=>'ellipsis','title'=>'{project_name}','data'=>'<a href="#cases/{project}">{project_name}</a>')),
			'subject'=>array('heading'=>'科目'),
			'amount'=>array('heading'=>'金额','parser'=>array('function'=>function($amount,$received){
				if($amount>0){
					return '<span style="color:#0A0">'.$amount.'</span> '.(intval($received)?'√':'？');
				}else{
					return '<span style="color:#A00">'.$amount.'</span> '.(intval($received)?'√':'？');
				}
			},'args'=>array('amount','received')),'cell'=>array('style'=>'text-align:right')),
			'date'=>array('heading'=>'日期'),
			'payer_name'=>array('heading'=>'付款/收款人')
		);

		$this->search_items=array('account','date/from','date/to','project_name','amount','payer_name','labels','project_labels','received','people','team','role');
	}
	
	function index(){
		
		$this->load->model('project_model','project');
		
		$this->config->set_user_item('search/show_project', true, false);
		$this->config->set_user_item('search/show_payer', true , false);
		$this->config->set_user_item('search/show_account', true, false);
		$this->config->set_user_item('search/orderby', 'account.time desc', false);
		$this->config->set_user_item('search/limit', 'pagination', false);
		$this->config->set_user_item('search/date/from', $this->date->year_begin, false);
		
		foreach($this->search_items as $item){
			if($this->input->post($item)){
				$this->config->set_user_item('search/'.$item, $this->input->post($item));
			}
			elseif($this->input->post('submit')==='search'){
				$this->config->unset_user_item('search/'.$item);
			}
		}
		
		if($this->input->post('submit')==='search_cancel'){
			foreach($this->search_items as $item){
				$this->config->unset_user_item('search/'.$item);
			}
		}
		
		if($this->config->user_item('search/role')){
			$this->list_args['weight']=array('heading'=>'占比');
		}
		
		$this->table->setFields($this->list_args)
			->setRowAttributes(array('hash'=>'account/{id}'))
			->setData($this->account->getList($this->config->user_item('search')));
		
		//总业绩表
		$summary=array(
			'_heading'=>array(
				'签约',
				'预计',
				'创收'
			),
			
			array(
				$this->account->getSum(array(
					'received'=>false,
					'limit'=>false,
					'orderby'=>false,
					'contract_date'=>array('from'=>$this->config->user_item('search/date/from'),'to'=>$this->config->user_item('search/date/to')),
					'ten_thousand_unit'=>true
				)+$this->config->user_item('search')),
				$this->account->getSum(array(
					'received'=>false,
					'limit'=>false,
					'orderby'=>false,
					'date'=>array('from'=>$this->config->user_item('search/date/from'),'to'=>$this->config->user_item('search/date/to')),
					'ten_thousand_unit'=>true
				)+$this->config->user_item('search')),
				$this->account->getSum(array(
					'received'=>true,
					'limit'=>false,
					'orderby'=>false,
					'date'=>array('from'=>$this->config->user_item('search/date/from'),'to'=>$this->config->user_item('search/date/to')),
					'ten_thousand_unit'=>true
				)+$this->config->user_item('search'))
			)
			
		);
		
		$this->load->addViewData('summary', $summary);
		
		$this->load->model('staff_model','staff');

		$this->load->view('list');
		$this->load->view('account/list_sidebar',true,'sidebar');
	}

	function add(){
		$this->account->id=$this->account->getAddingItem();
		
		if($this->account->id===false){
			$data=array('name'=>'律师费');

			if($this->input->get('project')){
				$data['project']=intval($this->input->get('project'));
			}
			if($this->input->get('client')){
				$data['people']=intval($this->input->get('client'));
			}
			
			$this->account->id=$this->account->add($data);
		}
		
		$this->edit($this->account->id);
	}

	function edit($id){
		$this->account->id=$id;
		
		$this->load->model('project_model','project');
		
		try{
			$this->account->data=$this->account->fetch($this->account->id);

			if($this->account->data['name']){
				$this->output->title=$this->account->data['name'];
			}else{
				$this->output->title='未命名'.lang(CONTROLLER);
			}
			
			$this->load->addViewData('account',$this->account->data);

			$this->load->view('account/edit');
			$this->load->view('account/edit_sidebar',true,'sidebar');
		}
		catch(Exception $e){
			$this->output->status='fail';
			if($e->getMessage()){
				$this->output->message($e->getMessage(), 'warning');
			}
		}

	}
	
	function submit($submit,$id){
		$this->account->id=$id;

		$this->account->data=array_merge($this->account->fetch($id),$this->input->sessionPost('account'));
		
		try{
			
			if($submit=='cancel'){
				unsetPost();
				$this->output->status='close';
			}
			
			if($submit=='account'){
				
				if($this->account->data['way']=='out'){
					$this->account->data['amount']=-abs($this->account->data['amount']);
				}
				//根据way设置amount的正负号
				
				if(!$this->account->data['name'] && $this->account->data['project']){
					$this->load->model('project_model','project');
					$this->account->data['name']=$this->project->fetch($this->account->data['project'],'name').' '.$this->account->data['subject'];
				}
				
				if(!$this->account->data['display']){
					$this->account->data['display']=true;
					$this->output->status='redirect';
					$this->output->data='account/'.$this->account->id;
				}

				$this->account->update($this->account->id,$this->account->data);
				
				unsetPost();
				
				$this->output->message($this->output->title.' 已保存');
			}
			
			if(is_null($this->output->status)){
				$this->output->status='success';
			}
			
		}catch(Exception $e){
			$this->output->status='fail';
		}
	}
}
?>