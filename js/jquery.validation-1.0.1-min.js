/*
 *
 * jQuery Validation 1.0.1
 * 
 * Date: Aug 16 2012
 * Source: www.tectual.com.au, www.arashkarimzadeh.com
 * Author: Arash Karimzadeh (arash@tectual.com.au)
 *
 * Copyright (c) 2012 Tectual Pty. Ltd.
 * http://www.opensource.org/licenses/mit-license.php
 *
 */
(function($){$.fn.validations=function(options){var opts=$.extend({},$.fn.validations.options,options);var selector=[];var errors=[];$.each(opts.validators,function(k,v){selector.push("[data-validate-"+k+"]");});$.each(opts.errorClasses,function(k,v){errors.push(v);});$('[data-validate]',this).each(function(){var btn=$(this);var form=$(btn.data('validate'));var validation=function($this,fcheck){var failed=false;var err=$($this.data('validate-error'));$.each(selector,function(k,v){if($this.is(v)){var name=v.split('-').pop().slice(0,-1);if(opts.validators[name].call($this,$this.data("validate-"+name),form)){err.removeClass(opts.errorClasses[name]);}else{failed=true;err.addClass(opts.errorClasses[name]);}};});if(failed){err.show();$this.attr('data-validate-failed',true);if(fcheck)opts.onError.call($this,form);}else{$this.removeAttr('data-validate-failed');err.hide();if(fcheck)opts.onResolve.call($this,form);}}
  $(selector.join(','),form).bind(opts.validateOn,function(){validation($(this),true)});var validate=function(){$(selector.join(','),form).each(function(){validation($(this),false);});opts.onComplete.call(form,btn);return $('[data-validate-failed]',form).size()==0;};form.data('validations.check',validate);btn.click(function(ev){if(!validate())ev.stopImmediatePropagation();});});return this;};$.fn.validate=function(){return this.data('validations.check')();};$.fn.validations.options={validateOn:'blur',onError:$.noop,onResolve:$.noop,onComplete:$.noop,errorClasses:{presence:'blank-error',format:'format-error',email:'email-error',confirmation:'confirm-error',checked:'checked-error',selected:'selected-error'},validators:{presence:function(){return this.val()!=''},format:function(value){return eval(value).test(this.val())},confirmation:function(value){return this.val()==$(value).val()},email:function(){return/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(this.val())},checked:function(v,form){var fields=$('[name="'+this.attr('name')+'"]',form);if(v==true)return fields.is(':checked');v=v.split(',');var cnt=0;fields.each(function(){if($(this).is(':checked'))cnt++;});return((v[0]=='*'||cnt>=parseInt(v[0]))&&(v[1]=='*'||cnt<=parseInt(v[1])));},selected:function(v){v=v.split(',');var cnt=$('option:selected',this).size();return(((v[0]=='*'||cnt>=parseInt(v[0]))&&(v[1]=='*'||cnt<=parseInt(v[1]))));}}}})(jQuery);
