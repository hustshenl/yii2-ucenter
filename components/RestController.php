<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/4/7 10:02
 * @Description:
 */

namespace hustshenl\ucenter\components;

use yii;
use yii\rest\Controller;
use yii\web\Response;


class RestController extends Controller {
    protected $_callback = false;
    public $serializer = [
        'class' => 'api\components\Serializer',
        'collectionEnvelope' => 'data',
    ];
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        if($this->jsonCallback) {
            $behaviors['contentNegotiator']['formats']['*/*'] = Response::FORMAT_JSONP ;
            $behaviors['contentNegotiator']['formats']['application/xml'] = Response::FORMAT_JSONP ;
            $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSONP ;
        }else{
            $behaviors['contentNegotiator']['formats']['*/*'] = Response::FORMAT_JSON ;
            $behaviors['contentNegotiator']['formats']['application/xml'] = Response::FORMAT_JSON ;
            $behaviors['contentNegotiator']['formats']['application/json'] = Response::FORMAT_JSON ;
        }

        $behaviors['corsFilter'] = [
            'class' => yii\filters\Cors::className(),
            'cors' => Yii::$app->params['cors'],
        ];
        return $behaviors;
    }

    public function error($data)
    {
        $this->serializer['customer'] = ['status'=>0];
        if(is_string($data)||is_numeric($data)) return [$this->serializer['collectionEnvelope'] =>$data];
        return $data;
    }
    public function success($data)
    {
        $this->serializer['customer'] = ['status'=>1];
        if(is_string($data)||is_numeric($data)) return [$this->serializer['collectionEnvelope'] =>$data];
        return $data;
    }
    public function getPostData()
    {
        $post = Yii::$app->request->post();
        Yii::trace($post);
        Yii::trace(Yii::$app->request->rawBody);
        Yii::trace(Yii::$app->request->get());
        if(empty($post)) $post = json_decode(Yii::$app->request->rawBody,true);
        if(empty($post)) $post = Yii::$app->request->get();
        return $post;
    }

    protected function getJsonCallback()
    {
        if($this->_callback) return $this->_callback;
        return $this->_callback = Yii::$app->request->get("callback",Yii::$app->request->get("jsoncallback",false));
    }

    protected function serializeData($data)
    {
        if($this->jsonCallback)
        {
            $result['data'] = parent::serializeData($data);
            $result['callback'] = $this->jsonCallback;
            return $result;
        }
        return parent::serializeData($data);
    }
    public function actionOptions()
    {
        return [];
    }
}