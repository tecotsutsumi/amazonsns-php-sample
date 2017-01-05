<?php
require_once __DIR__ . '/../vendor/autoload.php';

//■準備
//「~/.aws/credentials」に認証情報を置いておく。
//認証情報をアプリケーションの設定ファイルで管理する方法もある。
//詳細は以下参照。
//http://docs.aws.amazon.com/aws-sdk-php/v2/guide/credentials.html#credential-profiles

//設定値（運用時はアプリケーションの設定ファイルで管理）
$platformApplicationArn = 'arn:aws:sns:ap-northeast-1:889618090367:app/GCM/sns_test';
$snsClientConfig = [
    'profile' => 'default',
    'region' => 'ap-northeast-1',
    'version' => 'latest'
];

//SNSクライアントインスタンス化
$client = new Aws\Sns\SnsClient($snsClientConfig);

//画面表示制御用変数
$defaults = [
    'targetArn' => '',
    'token' => '',
    'message' => ''
];

//メッセージ送信=================================================================
if ($_POST['publish']) {
    try {
        $message = $_POST['message'];
        $targetArn = $_POST['targetArn'];
        // publish
        $msg = [
            'Message' => $message,
            'TargetArn' => $targetArn,
        ];
        $client->publish($msg);
    } catch (Aws\Sns\Exception\SnsException $e) {
        echo $e->getMessage();
        exit;
    }

    $defaults['targetArn'] = $_POST['targetArn'];
    $defaults['message'] = $_POST['message'];
}

//エンドポイント作成=================================================================
if ($_POST['createEndpoint']) {
    try {
        // エンドポイント登録
        $token = $_POST['token'];
        $params = [
            'PlatformApplicationArn' => $platformApplicationArn,
            'Token' => $token,
        ];
        $client->createPlatformEndpoint($params);
    } catch (Aws\Sns\Exception\SnsException $e) {
        echo $e->getMessage();
        exit;
    }

    $defaults['token'] = $_POST['token'];
}

//サブスクライブ=================================================================
if ($_POST['subscribe']) {
    try {
        $endpointArn = $_POST['endpointArn'];
        $topicArn = $_POST['topicArn'];
        $params = [
            'Endpoint' => $endpointArn,
            'Protocol' => 'application',
            'TopicArn' => $topicArn
        ];
        $client->subscribe($params);
    } catch (Aws\Sns\Exception\SnsException $e) {
        echo $e->getMessage();
        exit;
    }

    $defaults['token'] = $_POST['token'];
}

//トピックスに紐づいたサブスクリプションの確認=================================================================
$subscriptionsByTopic = null;
$topicArn = '';
if ($_GET['ListSubscription']) {
    try {
        $topicArn = $_GET['ListSubscription'];
        $params = [
            'TopicArn' => $topicArn
        ];
        $subscriptionsByTopic = $client->listSubscriptionsByTopic($params);
    } catch (Aws\Sns\Exception\SnsException $e) {
        echo $e->getMessage();
        exit;
    }
}

//トピック一覧取得=================================================================
$topics = $client->listTopics();

//エンドポイント一覧取得=================================================================
$endpoints = $client->listEndpointsByPlatformApplication([
    'PlatformApplicationArn' => $platformApplicationArn,
]);

?>

<html lang="jp">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Amazon SNS Demo</title>

    <!-- bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body>
    <div class="container">
        <h1>Amazon SNS Demo</h1>

        <h2 class="page-header">Publish</h2>
        <p>
            メッセージを送信する。EndpointArnを指定すれば個別に、TopicArnを指定すればTopicにサブスクライブされたエンドポイントにまとめて送信できる。
        </p>
        <form method="post">
            <div class="form-group">
                <label for="targetArn">targetArn</label>
                <input type="text" name="targetArn" id="targetArn" value="<?=$defaults['targetArn']?>" title="targetArn" class="form-control">
            </div>
            <div class="form-group">
                <label for="message">message</label>
                <input type="text" name="message" id="message" value="<?=$defaults['message']?>" title="message" class="form-control">
            </div>
            <input type="submit" value="publish" name="publish" class="btn btn-primary">
        </form>

        <h2 class="page-header">Endpoints</h2>
        <p>
            アプリケーションに登録されたエンドポイントの一覧。100件ずつ取得可能。このサンプルでは最初の100件のみ表示。
        </p>
        <table class="table table-bordered">
            <tr class="table-header">
                <th rowspan="2">EndpointArn</th>
                <th colspan="2">Attributes</th>
            </tr>
            <tr>
                <th>Enabled</th>
                <th>Token</th>
            </tr>
            <?php foreach ($endpoints['Endpoints'] as $endpoint) : ?>
                <tr>
                    <td><input class="form-control" value="<?=$endpoint['EndpointArn']?>" disabled></td>
                    <td><?=$endpoint['Attributes']['Enabled']?></td>
                    <td><input class="form-control" value="<?=$endpoint['Attributes']['Token']?>" disabled></td>
                </tr>
            <?php endforeach ?>
        </table>

        <h2 class="page-header">Create Platform Endpoint</h2>
        <p>
            デバイストークン、登録IDをアプリケーションに登録する。登録済みだった場合は何も起こらない。
        </p>
        <form method="post">
            <div class="form-group">
                <label for="token">token</label>
                <input type="text" name="token" id="token" value="<?=$defaults['token']?>" title="token" class="form-control">
            </div>
            <input type="submit" value="create" name="createEndpoint" class="btn btn-primary">
        </form>

        <h2 class="page-header">Topics</h2>
        <p>
            トピックスの一覧。100件ずつ取得可能。このサンプルでは最初の100件のみ表示。
        </p>
        <table class="table table-bordered">
            <tr>
                <th>Actions</th>
                <th>TopicArn</th>
            </tr>
            <?php foreach ($topics['Topics'] as $topic) : ?>
                <tr>
                    <td>
                        <a href="?ListSubscription=<?=urlencode($topic['TopicArn'])?>">ListSubscription</a>
                    </td>
                    <td><input class="form-control" value="<?=$topic['TopicArn']?>" disabled></td>
                </tr>
            <?php endforeach ?>
        </table>
        <?php if ($subscriptionsByTopic) : ?>
            <h2 class="page-header">Subscriptions by Topic</h2>
            <p>target topic : <?=$topicArn?></p>
            <p>
                トピックに紐づいたサブスクリプション（エンドポイント）の一覧。
            </p>
            <table class="table table-bordered">
                <tr>
                    <th>SubscriptionArn</th>
                    <th>Owner</th>
                    <th>Protocol</th>
                    <th>Endpoint</th>
                </tr>
                <?php foreach ($subscriptionsByTopic['Subscriptions'] as $subscription) : ?>
                    <tr>
                        <td><input class="form-control" value="<?=$subscription['SubscriptionArn']?>" disabled></td>
                        <td><?=$subscription['Owner']?></td>
                        <td><?=$subscription['Protocol']?></td>
                        <td><input class="form-control" value="<?=$subscription['Endpoint']?>" disabled></td>
                    </tr>
                <?php endforeach ?>
            </table>
        <?php endif ?>

        <h2 class="page-header">Subscribe</h2>
        <p>
            エンドポイントをトピックに登録する。
        </p>
        <form method="post">
            <div class="form-group">
                <label for="endpointArn">endpointArn</label>
                <input type="text" name="endpointArn" id="endpointArn" value="<?=$defaults['endpointArn']?>" title="endpointArn" class="form-control">
            </div>
            <div class="form-group">
                <label for="topicArn">topicArn</label>
                <input type="text" name="topicArn" id="topicArn" value="<?=$defaults['topicArn']?>" title="topicArn" class="form-control">
            </div>
            <input type="submit" value="subscribe" name="subscribe" class="btn btn-primary">
        </form>

        </table>
    </div>
</body>
</html>