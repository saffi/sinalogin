<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<script type="text/javascript" src="jquery-1.7.2.min.js"></script>
<title>上传图片</title>
</head>
<body>
	<form method="post" enctype="multipart/form-data" action="index.php">
		文件：<input type="file" name="file1"/><br/>
		文本消息 ：<textarea name="text" ></textarea><br/>
		邮箱：<input type="text"  value=""  name="username" /><br/>
		密码：<input type="password"  value=""  name="password" /><br/>
		<input type="submit" value="提交" name="upload"/><br/>
	</form>	
</body>
</html>