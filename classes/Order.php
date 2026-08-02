<?php declare(strict_types=1);
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Auth.php';
class Order {
 public static function cart(int $customerId): array { $s=Database::getConnection()->prepare('SELECT c.product_id,c.quantity,p.name,p.price,p.image FROM carts c JOIN products p ON p.id=c.product_id WHERE c.customer_id=:id AND p.status="active"');$s->execute(['id'=>$customerId]);return $s->fetchAll(); }
 public static function add(int $customerId,int $productId,int $qty=1): void { $p=Product::find($productId);if(!$p||$p['status']!=='active')throw new RuntimeException('محصول در دسترس نیست.');$q=max(1,min(99,$qty));$s=Database::getConnection()->prepare('INSERT INTO carts(customer_id,product_id,quantity) VALUES(:c,:p,:q) ON DUPLICATE KEY UPDATE quantity=LEAST(99,quantity+VALUES(quantity))');$s->execute(['c'=>$customerId,'p'=>$productId,'q'=>$q]); }
 public static function updateCart(int $customerId,int $productId,int $qty): void { $s=Database::getConnection()->prepare($qty<1?'DELETE FROM carts WHERE customer_id=:c AND product_id=:p':'UPDATE carts SET quantity=:q WHERE customer_id=:c AND product_id=:p');$a=['c'=>$customerId,'p'=>$productId];if($qty>0)$a['q']=min(99,$qty);$s->execute($a); }
 public static function place(int $customerId,string $note=''): int { $pdo=Database::getConnection();$items=self::cart($customerId);if(!$items)throw new RuntimeException('سبد خرید خالی است.');$pdo->beginTransaction();try{$total=0;foreach($items as $i)$total+=(int)$i['price']*(int)$i['quantity'];$number='DNJ-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));$s=$pdo->prepare('INSERT INTO orders(customer_id,order_number,total_price,customer_note) VALUES(:c,:n,:t,:note)');$s->execute(['c'=>$customerId,'n'=>$number,'t'=>$total,'note'=>mb_substr(trim($note),0,500)]);$id=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO order_items(order_id,product_id,product_name,quantity,price) VALUES(:o,:p,:n,:q,:price)');foreach($items as $i)$ins->execute(['o'=>$id,'p'=>$i['product_id'],'n'=>$i['name'],'q'=>$i['quantity'],'price'=>$i['price']]);$pdo->prepare('DELETE FROM carts WHERE customer_id=:c')->execute(['c'=>$customerId]);$pdo->commit();ActivityLog::record('order_create','customer',$customerId,$_SESSION['customer_display_name']??null,$id,'ثبت سفارش '.$number);return $id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
 public static function mine(int $customerId):array{$s=Database::getConnection()->prepare('SELECT * FROM orders WHERE customer_id=:c ORDER BY id DESC');$s->execute(['c'=>$customerId]);return $s->fetchAll();}
 public static function findMine(int $id,int $customerId):?array{$s=Database::getConnection()->prepare('SELECT * FROM orders WHERE id=:id AND customer_id=:c');$s->execute(['id'=>$id,'c'=>$customerId]);$o=$s->fetch();if(!$o)return null;$x=Database::getConnection()->prepare('SELECT * FROM order_items WHERE order_id=:id');$x->execute(['id'=>$id]);$o['items']=$x->fetchAll();return $o;}
 public static function findById(int $id): ?array { $s=Database::getConnection()->prepare('SELECT o.*, c.phone, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.id=:id');$s->execute(['id'=>$id]);$o=$s->fetch();if(!$o) return null;$x=Database::getConnection()->prepare('SELECT * FROM order_items WHERE order_id=:id');$x->execute(['id'=>$id]);$o['items']=$x->fetchAll();return $o; }
 public static function all():array{return Database::getConnection()->query('SELECT o.*, c.phone, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name FROM orders o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC')->fetchAll();}
 public static function status(int $id,string $status,?int $actorId=null,?string $actorLabel=null,string $role='admin', ?string $paymentMethod = null):void{
  if(!in_array($status,['pending','approved','rejected','completed'],true))throw new RuntimeException('وضعیت نامعتبر است.');
  $actorId=$actorId??Auth::id();$actorLabel=$actorLabel??Auth::username();
  if ($status === 'approved') {
      $sql = 'UPDATE orders SET status=:s, approved_at=NOW() WHERE id=:i';
      Database::getConnection()->prepare($sql)->execute(['s'=>$status,'i'=>$id]);
  } elseif ($status === 'completed') {
      // when completing, optionally record payment method
      $sql = 'UPDATE orders SET status=:s, approved_at=NOW(), payment_method=:pm WHERE id=:i';
      Database::getConnection()->prepare($sql)->execute(['s'=>$status,'pm'=>$paymentMethod,'i'=>$id]);
  } else {
      $sql = 'UPDATE orders SET status=:s, payment_method=NULL WHERE id=:i';
      Database::getConnection()->prepare($sql)->execute(['s'=>$status,'i'=>$id]);
  }
  $action=['approved'=>'order_approve','rejected'=>'order_reject'][$status]??'order_status';
  ActivityLog::record($action,$role,$actorId,$actorLabel,$id,'تغییر وضعیت سفارش به «'.$status.'»');
 }
 public static function assignBarista(int $id,?int $baristaId,?int $actorId=null,?string $actorLabel=null):void{
  Database::getConnection()->prepare('UPDATE orders SET barista_id=:b WHERE id=:i')->execute(['b'=>$baristaId,'i'=>$id]);
  ActivityLog::record('order_barista','admin',$actorId??Auth::id(),$actorLabel??Auth::username(),$id,$baristaId?'اختصاص باریستا به سفارش':'حذف باریستای سفارش');
 }
 public static function deletePending(int $id,int $customerId): bool {
  $order=self::findMine($id,$customerId);
  $s=Database::getConnection()->prepare("DELETE FROM orders WHERE id=:id AND customer_id=:customer_id AND status='pending'");
  $s->execute(['id'=>$id,'customer_id'=>$customerId]);
  $deleted=$s->rowCount()===1;
  if($deleted)ActivityLog::record('order_delete','customer',$customerId,$_SESSION['customer_display_name']??null,$id,'حذف سفارش '.($order['order_number']??''));
  return $deleted;
 }

 /** گزارش سفارش‌ها با جستجو/فیلتر/مرتب‌سازی — برای صفحهٔ مدیریت سفارش‌ها */
 public static function report(array $f=[]):array{
  $where=[];$params=[];
  if(!empty($f['status'])){$where[]='o.status=:status';$params['status']=$f['status'];}
  if(!empty($f['barista_id'])){$where[]='o.barista_id=:barista_id';$params['barista_id']=(int)$f['barista_id'];}
  if(!empty($f['q'])){$where[]='(o.order_number LIKE :q1 OR c.phone LIKE :q2 OR CONCAT_WS(" ",c.first_name,c.last_name) LIKE :q3)';$params['q1']=$params['q2']=$params['q3']='%'.$f['q'].'%';}
  if(!empty($f['from'])){$where[]='o.created_at >= :from';$params['from']=$f['from'].' 00:00:00';}
  if(!empty($f['to'])){$where[]='o.created_at <= :to';$params['to']=$f['to'].' 23:59:59';}
  $sortMap=['date_desc'=>'o.created_at DESC','date_asc'=>'o.created_at ASC','amount_desc'=>'o.total_price DESC','amount_asc'=>'o.total_price ASC'];
  $order=$sortMap[$f['sort']??'date_desc']??$sortMap['date_desc'];
  $sql='SELECT o.*, c.phone, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name, b.full_name AS barista_name FROM orders o JOIN customers c ON c.id=o.customer_id LEFT JOIN baristas b ON b.id=o.barista_id';
  if($where)$sql.=' WHERE '.implode(' AND ',$where);
  $sql.=' ORDER BY '.$order;
  $stmt=Database::getConnection()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
 }

 /** آمار سفارش‌های امروز/در انتظار/تأییدشده/تکمیل‌شده — برای داشبورد */
 public static function dashboardCounts():array{
  $pdo=Database::getConnection();
  $today=(int)$pdo->query('SELECT COUNT(*) c FROM orders WHERE DATE(created_at)=CURDATE()')->fetch()['c'];
  $byStatus=$pdo->query("SELECT status, COUNT(*) c FROM orders GROUP BY status")->fetchAll();
  $counts=['pending'=>0,'approved'=>0,'rejected'=>0,'completed'=>0];
  foreach($byStatus as $r)$counts[$r['status']]=(int)$r['c'];
  return ['today'=>$today]+$counts;
 }

 /** تعداد سفارش هر باریستا در ۱۴ روز اخیر — برای نمودار داشبورد */
 public static function dailyCounts(int $days=14):array{
  $stmt=Database::getConnection()->prepare('SELECT DATE(created_at) d, COUNT(*) c FROM orders WHERE created_at >= :from GROUP BY DATE(created_at)');
  $stmt->execute(['from'=>date('Y-m-d',strtotime('-'.($days-1).' days'))]);
  $rows=array_column($stmt->fetchAll(),'c','d');
  $out=[];
  for($i=$days-1;$i>=0;$i--){$d=date('Y-m-d',strtotime("-$i days"));$out[$d]=(int)($rows[$d]??0);}
  return $out;
 }
}
