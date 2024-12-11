function setupOutline(data) {
    for(var list=data['lists'].length;list--;) {
      for(var i=data['lists'][list].length;i--;) {
        var $item = $('.pages').find('[title="'+data['lists'][list][i].id+'"]');
        $item.data('item', data['lists'][list][i]);
        $item.click(function (e) {
          e.preventDefault();
          // VUDL DOWNLOAD ON CLICK
          if ($(this).hasClass('selected') && $(this).find('.fa.fa-file').length > 0) {
            $('#file-download').submit();
            return false;
          }
          var data = $(this).data('item');
          if (currentView === data['filetype']) {
            updateView(data);
          } else {
            currentView = data['filetype'];
            ajaxGetView(data);
          }
          getTechInfo(data);
          $('.pages .item.selected').removeClass('selected');
          $(this).addClass('selected loading');
          return false;
        });
      }
    }
  }
  // ====== GET VIEWS ====== //
  var viewLoadAjax = false;
  var currentTab = 'medium';
  function ajaxGetView(pageObject) {
    if(viewLoadAjax) {
      viewLoadAjax.abort();
    }
    viewLoadAjax = $.ajax({
      type: 'POST',
      url : VuFind.path+'/VuDL/ajax?method=viewLoad',
      data: pageObject,
      success: function(e) {
        $('#view').html(e.data);
        currentType = pageObject['filetype'];
        if (typeof currentTab === "undefined") {
          currentTab = $('.nav-tabs li a:eq(0)')[0].id;
        }
        $('#'+currentTab, e.data).click();
      },
      error: function(d,e){
        console.log(d.responseText);
        console.log(e);
      },
      dataType: 'json'
    });
  }
  
  // Pages
  function prevPage() {
    $('.item.selected').prev('.item').click();
    scrollToSelected();
  }
  function nextPage() {
    $('.item.selected').next('.item').click();
    scrollToSelected();
  }
  function scrollToSelected() {
    var listID = '#collapse'+currentList;
    if($(listID).length > 0 && $(listID+' .selected').length > 0) {
      $(listID).finish();
      scrollAnimation = $(listID).animate({
        scrollTop: $(listID+' .selected').offset().top-$(listID).offset().top+$(listID).scrollTop()-12
      });
    }
  }
  // Toggle side menu
  function toggleSideNav() {
    $('#side-nav').toggle();
    var opener = $('#view .nav-tabs li.opener a');
    opener.toggleClass('hidden');
    $('#view').toggleClass('col-sm-9').toggleClass('col-sm-12');
  }
  
  function resizeElements() {
    var $height = $(window).height() + window.scrollY - $('.panel-collapse.in').offset().top - 50;
    $('.panel-collapse').css('max-height', Math.max(300, Math.min($height, $(window).height() - 200)));
  }
  
  // Ready? Let's go
  $(document).ready(function() {
    $('#side-nav-toggle').click(toggleSideNav);
    scrollToSelected();
    resizeElements();
    $( window ).resize( resizeElements );
    $( window ).scroll( resizeElements );
  });
  
  $(document).on('show.bs.collapse', function(e) {
    var $list = $(e.target);
    if($list.find('.item').length > 1) {
      $('.sibling-form .turn-button').removeClass('hidden');
    } else {
      $('.sibling-form .turn-button').addClass('hidden');
    }
  });