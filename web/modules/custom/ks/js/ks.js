'use strict';
 
 //General
function webformInit($, context, once){
  var webform = $('form[id^="webform-submission"]');
  if(webform.length < 1) return;
      
  function cardChange(event, card){
    var idCard = card.data('webformKey');
    if(idCard.indexOf('revisar') > -1){      
      $('[data-drupal-selector="edit-revisar-trigger"]').change();
    }else if(idCard.indexOf('contrato_clausulas') > -1){
      $('.ct-mensaje-revisar .form-item--error-message').remove();
      if($('#koe-pago-popup').length){
        $.magnificPopup.open({type:'inline', items: {src: '#koe-pago-popup'}});
      }      
    }
  }
  once('ksBehavior', 'form[id^="webform-submission"]').forEach(function(element){ $(element).on('webform_cards:change', cardChange); });
  
  var referidos_container = $('#koe-referidos-container', context);
  if(referidos_container.length){
    var dato = referidos_container.data('dato');
    if($('#koe-referidos-popup').length){      
      $('#koe-referidos-popup').html($('#koe-referidos-popup').html().replace('[dato]', dato));
      $.magnificPopup.open({type:'inline', items: {src: '#koe-referidos-popup'}});
    }
    if($('#koe-referidos-cta').length){
      $('#koe-referidos-cta').html($('#koe-referidos-cta').html().replace('[dato]', dato));
      referidos_container.append($('#koe-referidos-cta'));
      $('#koe-referidos-cta').removeClass('mfp-hide');
    }
  }
}

//Contrato
function contratoInit($, context, once){
  var contratoForm = $('form[id^="webform-submission-contrato"]');
  if(contratoForm.length < 1) return;
  
  $('.contrato-pago-link').magnificPopup({type:'iframe'});
    
  var beneficiariosUnoCopiarFields = [['rfc', 'usuario'], 'edad', 'nombre', 'correo', 'nacimiento', 'celular', 'telefono', 'domicilio', 'ncalle', 'ncasa', 'ciudad', 'calles'];
  function beneficiarioUnoCopiarClick(event){
    var beneficiariosUnoCopiarBtn = $('#beneficiarios-uno-copiar');
    var oldLabel = beneficiariosUnoCopiarBtn.text();
    beneficiariosUnoCopiarBtn.text('¡COPIADO!');
    setTimeout(function() { beneficiariosUnoCopiarBtn.text(oldLabel);}, 1500);
    
     for(var i = 0; i < beneficiariosUnoCopiarFields.length; i++){
        var field = beneficiariosUnoCopiarFields[i];
        var compradorField = (typeof field == 'string')? 'comprador-'+field:'comprador-'+field[0];
        var beneficiarioUnoField = (typeof field == 'string')? 'beneficiarios-uno-'+field:'beneficiarios-uno-'+field[1];
        $('[data-drupal-selector="edit-'+beneficiarioUnoField+'"]').val($('[data-drupal-selector="edit-'+compradorField+'"]').val());
     }
          
     $('[data-drupal-selector="edit-beneficiarios-uno-edad"]').change();
  }
  once('ksBehavior', '#beneficiarios-uno-copiar').forEach(function(element){ $(element).click(beneficiarioUnoCopiarClick); }); 
  
  function beneficiarioDosCopiarClick(event){
    var beneficiariosDosCopiarBtn = $('#beneficiarios-dos-copiar');
    var oldLabel = beneficiariosDosCopiarBtn.text();
    beneficiariosDosCopiarBtn.text('¡COPIADO!');
    setTimeout(function() { beneficiariosDosCopiarBtn.text(oldLabel);}, 1500);      
    
     $('[data-drupal-selector="edit-beneficiarios-dos-domicilio"]').val($('[data-drupal-selector="edit-beneficiarios-uno-domicilio"]').val());
     $('[data-drupal-selector="edit-beneficiarios-dos-ncalle"]').val($('[data-drupal-selector="edit-beneficiarios-uno-ncalle"]').val());
     $('[data-drupal-selector="edit-beneficiarios-dos-ncasa"]').val($('[data-drupal-selector="edit-beneficiarios-uno-ncasa"]').val());
     $('[data-drupal-selector="edit-beneficiarios-dos-ciudad"]').val($('[data-drupal-selector="edit-beneficiarios-uno-ciudad"]').val());
     $('[data-drupal-selector="edit-beneficiarios-dos-calles"]').val($('[data-drupal-selector="edit-beneficiarios-uno-calles"]').val());         
  }
  once('ksBehavior', '#beneficiarios-dos-copiar').forEach(function(element){ $(element).click(beneficiarioDosCopiarClick); });
  
  /* function pagareChange(event){
    $('[data-drupal-selector="edit-condiciones-plan"]').change();
  }
  $('[data-drupal-selector="edit-pagare"]').change(pagareChange); //presumiblemente ya no se requiere
  */  
}

//Contratos View (reporte)
function contratosViewInit($, context, once){
  var contratosViewElements = $('button.contrato-accion', context);
  if(contratosViewElements.length < 1) return;
  
  function resetClick(event){      
    event.preventDefault();
    window.location.reload();
    return false;
  }
  once('ksBehavior', '.view-id-contratos input#edit-reset').forEach(function(element){ $(element).click(resetClick); });
  
  function accionClick(event){
    var accion = $(this).data('accion');
    var url = $(this).data('url');
    
    var mensaje = '';
    switch(accion){
      case 'extender':
        mensaje = 'Se extenderá el tiempo de aceptación del contrato.';
      break;
      case 'editar':
        window.location.href = url;
        return;
      break;
      case 'cancelar':
        mensaje = 'Se habilitará la edición del contrato para guardarse con un nuevo folio.';
      break;
      case 'legalizar':
        mensaje = 'Se legalizará el contrato en el SISK.';
      break;
      default:
        mensaje = 'El contrato pasará a estatus revisión.';
      break;
    }
    
    function accionResponse(response){
        if(response && response.message) alert(response.message);
        window.location.reload();
     }
     if(window.confirm(mensaje)){
       $.magnificPopup.open({modal: true, items: {src:'#km-modal-loading'}});
       $.get(url, {}, accionResponse);
     }
  }  
  once('ksBehavior', 'button.contrato-accion').forEach(function(element){ $(element).click(accionClick); });
}

//Bitácora
function bitacoraViewInit($, context, once){
  var bitacoraView = $('div.view-id-bitacora', context);  
  if(bitacoraView.length < 1) return;
  
  function accionClick(event){      
    function accionResponse(response){
        if(response && response.message) alert(response.message);
        window.location.reload();
     }
     if(window.confirm('Se reenviará el correo electrónico')){
       $.magnificPopup.open({modal: true, items: {src:'#km-modal-loading'}});
       $.get($(this).data('url'), {}, accionResponse);
     }
  }
  once('ksBehavior', 'button.bitacora-accion').forEach(function(element){ $(element).click(accionClick); });
}

(function ($, Drupal, once) {
  Drupal.behaviors.ksBehavior = {
    attach: function (context, settings) {      
      webformInit($, context, once);
      contratoInit($, context, once);
      contratosViewInit($, context, once);
      bitacoraViewInit($, context, once);
    }
  };
  
  Drupal.webform = Drupal.webform || {};
  Drupal.webform.cards = Drupal.webform.cards || {};
  Drupal.webform.cards.showAllCardsOnError = false;
  
})(jQuery, Drupal, once);