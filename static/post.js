
if (yaoyue_js_flag == "setting"){
    jQuery("#yaoyue-library-test-button").unbind('click').bind('click',(sys_library_ajax));
    }
    else {
        jQuery("#yaoyue-img-test-button").unbind('click').bind('click',(sys_ajax));
        // document.getElementById('yaoyue-img-test-button').addEventListener('click',sys_ajax)
    }
    function sys_library_ajax(){
        jQuery.ajax({ 
            type: "POST", 
            async:true,
            url: "/wp-admin/admin-ajax.php", 
            data: { 
             action: 'yaoyue_image_replace', 
           }, 
           success:function(res){
           },
           error: function (err){
           } 
           }); 
    }
    function sys_ajax(){
            jQuery.ajax({ 
             type: "POST", 
             async:true,
             url: "/wp-admin/admin-ajax.php", 
             data: { 
              action: 'yaoyue_test', 
              yaoyue: post_id
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