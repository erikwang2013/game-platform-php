// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:game_platform/app/services/chat_service.dart';

/// 载荷逐字取自 service/app/api/v1/controller/ChatController.php:137-146
/// （send() 里发布到 chat:delivery_queue，由 ChatWebSocket::deliverToUser 投给接收者）。
/// 站在【接收者=本客户端】视角：message.from_user_id 是对端 hashid，
/// 信封 to_user_id 是【自己的裸 int 用户 id】——后者只用于 WS 进程投递路由。
const String _pushJson = '{"type":"message",'
    '"message":{"id":"aBcD12","from_user_id":"kR3nQ8",'
    '"content":"晚上开黑？","created_at":"2026-09-28 21:40:00"},'
    '"to_user_id":12345}';

Map<String, dynamic> _pushPayload() => jsonDecode(_pushJson) as Map<String, dynamic>;

void main() {
  const peerHashid = 'kR3nQ8'; // chat_page 的 _peerId 就是它（friend_page 传的 f['id']）

  test('WS 推送按 message.from_user_id 入桶：旧判据（信封 to_user_id）永远查不到', () {
    final push = _pushPayload();

    // 反事实：HEAD 的 _decodePeerId 取的是 int.tryParse(msg['to_user_id'])
    final oldKey = int.tryParse(push['to_user_id'].toString());
    expect(oldKey, 12345); // 拿到的是【自己】的 id，不是对端
    expect(oldKey, isNot(peerHashid)); // 且与页面的 hashid 键不同域 ⇒ mergeFor 恒空

    final chat = ChatService();
    chat.handlePush(push);

    // 桶的键集合恰是【对端 hashid】——不是 12345（自己的 id），也不是它的字符串形式
    expect(chat.messagesByPeer.keys.toList(), [peerHashid]);
    // 页面读到的条数：旧 0 → 新 1
    expect(chat.mergeFor(peerHashid, const []).length, 1);
    expect(chat.mergeFor(peerHashid, const [])[0]['content'], '晚上开黑？');
    // 自己发的消息走 REST 回读，不该出现在推送桶里
    expect(chat.mergeFor('someoneElse', const []), isEmpty);
  });

  test('同一条推送重复到达不重复入桶（按 id 去重）', () {
    final chat = ChatService();
    chat.handlePush(_pushPayload());
    chat.handlePush(_pushPayload());

    expect(chat.mergeFor(peerHashid, const []).length, 1);
  });

  test('REST 已带回的消息不再重复渲染，且推送追加在 REST 之后', () {
    final chat = ChatService();
    final m = _pushPayload()['message'] as Map<String, dynamic>;
    chat.handlePush(_pushPayload());

    // 刷新后 REST 已包含这条（同 id），不得双份
    final rest = [
      {'id': 'older01', 'from_user_id': peerHashid, 'content': '在吗', 'created_at': '2026-09-28 21:00:00'},
      m,
    ];
    final shown = chat.mergeFor(peerHashid, rest);
    expect(shown.length, 2);
    expect(shown.map((e) => e['content']).toList(), ['在吗', '晚上开黑？']);

    // REST 还没带回时：REST 在前、推送在后（REST 是时间升序）
    final pending = chat.mergeFor(peerHashid, [rest[0]]);
    expect(pending.map((e) => e['content']).toList(), ['在吗', '晚上开黑？']);

    // 无推送时原样返回，不改动调用方持有的列表
    expect(chat.mergeFor('nobody', rest), same(rest));
  });

  test('畸形推送被丢弃，不制造空桶', () {
    final chat = ChatService();
    chat.handlePush({'type': 'message'}); // 无 message 体
    chat.handlePush({'type': 'message', 'message': 'not a map'});
    chat.handlePush({'type': 'message', 'message': {'id': 'x1', 'content': 'no sender'}});
    chat.handlePush({'type': 'message', 'message': {'id': 'x2', 'from_user_id': '', 'content': 'empty'}});
    chat.handlePush({'type': 'message', 'message': {'id': 'x3', 'from_user_id': 12345, 'content': 'int sender'}});

    expect(chat.messagesByPeer.isEmpty, isTrue);
  });
}
