'use strict';
 
 //General
function itiInit($, context, once){  
  //Iti intl-tel-input
  function itiInit(element){
    let elementId = $(element).attr('id');
    let iti = $(element).clone();
    iti.attr('id', elementId+'-iti');
    iti.attr('data-drupal-selector', elementId+'-iti');
    iti.attr('name', elementId+'-iti');
    
    iti.data('elementId', elementId);
    iti.data('element', element);
    
    $(element).after(iti);
    $(element).hide();   
    
    let itiObject = window.intlTelInput(iti[0], {initialCountry: "pa", preferredCountries: ['pa', 'us'], strictMode: true, loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input/build/js/utils.js')});
    iti.data('itiObject', itiObject);
    function telChange(event){
      let iti = $(event.currentTarget);
      iti[0].value.trim();
      
      let element = iti.data('element');
      let itiObject = iti.data('itiObject');
      
      let isValid = itiObject.isValidNumber();
      
      $(element).val(isValid? itiObject.getNumber():'');
    }
    iti.blur(telChange);
  }  
  let telFields = ['comprador_telefono', 'comprador_empresa_telefono', 'comprador_celular', 'pagare_telefono', 'pagare_empresa_telefono', 'pagare_celular', 'beneficiarios_uno_celular', 'beneficiarios_uno_telefono', 'beneficiarios_dos_celular', 'beneficiarios_dos_telefono'];
  for(let i = 0; i < telFields.length; i++){
    let fieldId = 'edit-'+telFields[i].replace(/_/g, '-');    
    once('itiBehavior', '[data-drupal-selector="'+fieldId+'"]').forEach((element) => itiInit(element));     
  } 
}

(function ($, Drupal, once) {
  Drupal.behaviors.itiBehavior = {
    attach: function (context, settings) {      
      itiInit($, context, once);
    }
  };
})(jQuery, Drupal, once);