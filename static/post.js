
if (lsky_js_flag == "page"){
        jQuery("#lsky-upload-one").unbind('click').bind('click',(sys_ajax));
    }
    function sys_ajax(){
            jQuery.ajax({ 
             type: "POST", 
             async:true,
             url: "/wp-admin/admin-ajax.php", 
             data: { 
              action: 'lsky_upload_one', 
              post_id: post_id
            }, 
            success:function(res){
                console.log(res.slice(0,-1));
                alert('替换成功');
            },
            error: function (err){
                console.log(err)
            }
            }); 
    }