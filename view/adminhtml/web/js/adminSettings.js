require(['jquery', 'jquery/ui'], function($){
    var $m = $.noConflict();
    $m(document).ready(function() {

        $m("#lk_check1").change(function(){
            if($("#lk_check2").is(":checked") && $("#lk_check1").is(":checked")){
                $("#activate_plugin").removeAttr('disabled');
            }
        });

        $m("#lk_check2").change(function(){
            if($("#lk_check2").is(":checked") && $("#lk_check1").is(":checked")){
                $("#activate_plugin").removeAttr('disabled');
            }
        });

        $m(".navbar a").click(function() {
            $id = $m(this).parent().attr('id');
            setactive($id);
            $href = $m(this).data('method');
            voiddisplay($href);
        });
        $m(".btn-link").click(function() {
            $m(this).siblings('.show_info').slideToggle('slow');
            
        });
        $m('#idpguide').on('change', function() {
            var selectedIdp =  jQuery(this).find('option:selected').val();
            $m('#idpsetuplink').css('display','inline');
            $m('#idpsetuplink').attr('href',selectedIdp);
        });
        $m("#mo_saml_add_shortcode").change(function(){
            $m("#mo_saml_add_shortcode_steps").slideToggle("slow");
        });
        $m('#error-cancel').click(function() {
            $error = "";
            $m(".error-msg").css("display", "none");
        });
        $m('#success-cancel').click(function() {
            $success = "";
            $m(".success-msg").css("display", "none");
        });
        $m('#cURL').click(function() {
            $m(".help_trouble").click();
            $m("#cURLfaq").click();
        });
        $m('#help_working_title1').click(function() {
            $m("#help_working_desc1").slideToggle("fast");
        });
        $m('#help_working_title2').click(function() {
            $m("#help_working_desc2").slideToggle("fast");
        });

    });
});

function setactive($id) {
    $m(".navbar-tabs>li").removeClass("active");
    $id = '#' + $id;
    $m($id).addClass("active");
}

function voiddisplay($href) {
    $m(".page").css("display", "none");
    $m($href).css("display", "block");
}

function mosp_valid(f) {
    !(/^[a-zA-Z?,.\(\)\/@ 0-9]*$/).test(f.value) ? f.value = f.value.replace(/[^a-zA-Z?,.\(\)\/@ 0-9]/, '') : null;
}

function ifUserRegistered(){
    if (document.getElementById('registered').checked){
        jQuery('#confirmPassword').css('display','none');
        jQuery('#firstName').css('display','none');
        jQuery('#lastName').css('display','none');
        jQuery('#company').css('display','none');
    } else {
        jQuery('#confirmPassword').css('display','block');
        jQuery('#firstName').css('display','block');
        jQuery('#lastName').css('display','block');
        jQuery('#company').css('display','block');
    }

}
function supportAction(){
}

function kba_div(){
    var element = document.getElementById('hide_show_kba_div');
    var element1 = document.getElementById('avaliable_in_premium_kba');
  if(element.style.display === "none"){
      element.style.display = "block";
      element1.style.display = "block";
  
  }else{
      element.style.display = "none";
      element1.style.display = "none";
  }

    }

    function popup_ui_div(){
        var element = document.getElementById('popup_ui_div');
        var element1 = document.getElementById('avaliable_in_premium_popup');
      
      if(element.style.display === "none"){
          element.style.display = "block";
          element1.style.display = "block";
      
      }else{
          element.style.display = "none";
          element1.style.display = "none";
      }
        }
      
        function customerinline_div(){
         
          var element1 = document.getElementById('avaliable_in_premium_inline');
        if(element1.style.display === "none"){
      
            element1.style.display = "block";
        
        }else{
      
            element1.style.display = "none";
        }
      
          }
      


    function adminrole_method_premium(){
        selectElement = document.querySelector('#twofa_role');
          output = selectElement.value;
        console.log(output);
       
        var element3 = document.getElementById('premium_admin_role');
        if(output=='Administrators'){
          element3.style.display="none";
        }else{
          element3.style.display="block";
        }
      }

      function customGatewayMethod() {
        var x = document.getElementById("customgatewayapiProvidersms").value;
        var a = document.getElementById("twilio_method");
        var b = document.getElementById("get_method");
        var c = document.getElementById("post_method");
      
        if(x=='twilio'){
          a.style.display = "block";
          b.style.display = "none";
          c.style.display = "none";
        }
        if(x=='getMethod'){
          b.style.display = "block";
          a.style.display = "none";
          c.style.display = "none";
        }
        if(x=='postMethod'){
          c.style.display = "block";
          a.style.display = "none";
          b.style.display = "none";
        }
      }
      
        function addCustomAttribute(){
      
          var param = jQuery("#post_parameter").val();
          var val = jQuery("#post_value").val();
          var div = generate(param,val)
           jQuery("#submit_custom_attr").before(div);
           jQuery("#post_parameter").val("");
           jQuery("#post_value").val("");
      
      }
      
      function generate(param,val){
          var attributeDiv =  jQuery("<div>",{"class":"gm-div","style":"margin-top:18px","id":"Div"});
          var labelForAttr = jQuery("<strong>",{"class":"form-control gm-input","style":"margin-left:0px; margin-top:8px;width:185px","type":"text", "placeholder":"Enter name of IDP attribute"}).text(param);
          var inputAttr = jQuery("<input>",{"id":param,"name":param,"class":"form-control gm-input","style":"margin-left:212px; margin-top:8px; position:absolute; padding:7px","type":"text", "placeholder":"Enter name of IDP attribute","value":val});
          attributeDiv.append(labelForAttr);
          attributeDiv.append(inputAttr);
      
          return attributeDiv;
      
      }
      
      function deleteCustomAttribute(){
      
              jQuery("#Div").remove();
      
      }
      