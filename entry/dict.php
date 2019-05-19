<?php
/*
 * *数据字典之类的参数
 */
namespace entry;
const COMM_DB_DSN = 'pgsql:host=127.0.0.1;port=5432;dbname=auto;user=postgres;password=password';
const COMM_DBCLUSTER=[
    '00'=>['pgsql:host=127.0.0.1;port=5432;dbname=auto;user=postgres;password=password', COMM_DB_DSN],
    '01'=>COMM_DB_DSN
];

const COMM_CODE_SPLIT='|';
const COMM_PHOTO_TYPE=['image/gif', 'image/jpeg', 'image/bmp', 'image/pjpeg'];
const COMM_WEIXIN_APPID='wxd678efh567hg6787';
const COMM_WEIXIN_MCHID='1230000109';
const COMM_WEIXIN_APIKEY='192006250b4c09247ec02edce69f6a2d';
const COMM_POST_KEY='ngqpem5gcppys39i2jvlb60nzakwvcrw';
const COMM_WEIXIN_UNIFIEORDERURL='https://api.mch.weixin.qq.com/pay/unifiedorder';
const COMM_WEIXIN_REFUNDURL='https://api.mch.weixin.qq.com/secapi/pay/refund';
const COMM_WEIXIN_GETCASHURL='https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
const COMM_DEBUG_FLAG=true;
const WEIXIN_ERRCODE_INFO=[
    ''=>'',
    ];

const COMM_UPDATE_USEBAL=1;
const COMM_UPDATE_CANUSEBAL=2;
const COMM_UPDATE_USECANUSEBAL=3;



