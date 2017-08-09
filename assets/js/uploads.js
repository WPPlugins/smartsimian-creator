jQuery(document).ready(function($){

    // if upload container exists
    if($(".plupload-upload-uic").length > 0) {

        var pconfig=false;

        // foreach upload container
        $(".plupload-upload-uic").each(function() {

            var $this=$(this);
            var idBuild=$this.attr('id');

            // get field name
            var fileid=idBuild.replace("-plupload-upload-ui", "");

            // generate existing file info
            plu_show_file_info(fileid);

            // set up config
            pconfig=JSON.parse(JSON.stringify(base_plupload_config));

            // set up remaining config - this is to recognize more than one upload button
            pconfig["browse_button"] = fileid + pconfig["browse_button"];

            pconfig["container"] = fileid + pconfig["container"];
            pconfig["drop_element"] = fileid + pconfig["drop_element"];
            pconfig["file_data_name"] = fileid + pconfig["file_data_name"];
            pconfig["multipart_params"]["fileid"] = fileid;
            pconfig["multipart_params"]["_ajax_nonce"] = $this.find(".ajax_nonce_span").attr("id").replace("ajax_nonce_span-", "");

            // max files allowed
            var maxFiles = $this.parent().find("#" + fileid + "-amount").val();
            var existingFiles = 0;
            var limitFiles = false;

            if($this.hasClass("plupload-upload-uic-multiple")) {
                pconfig["multi_selection"]=true;
            }

            if($this.find(".plupload-resize").length > 0) {
                var w=parseInt($this.find(".plupload-width").attr("id").replace("plupload-width-", ""));
                var h=parseInt($this.find(".plupload-height").attr("id").replace("plupload-height-", ""));
                pconfig["resize"]={
                    width : w,
                    height : h,
                    quality : 90
                };
            }

            var uploader = new plupload.Uploader(pconfig);

            uploader.bind('Init', function(up){
                up.splice();
            });

            uploader.init();
            uploader.splice();

            // a file was added in the queue
            uploader.bind('FilesAdded', function(up, files){

                // remove prior error messages
                $('.plupload-error').hide();

                // get existing files
                existingFiles = $this.next('.plupload-thumbs').find('.thumb').length;

                // # of uploads still allowed (if maxFiles==0 there's no limit)
                if (maxFiles>0)
                    limitFiles = maxFiles - existingFiles;

                // restrict amount of files in queue
                if( limitFiles !== false && up.files.length>limitFiles ) {

                    // Removing the extra files
                    while(up.files.length > limitFiles){
                        if(up.files.length > limitFiles)
                            uploader.removeFile(up.files[limitFiles]);
                    }

                    // error message
                    $this.find('.filelist').append( '<div class="plupload-error">Maximum uploads allowed for this field is ' + maxFiles + '. <a href="#">Dismiss</a></div>' );

                    up.splice();

                } else {

                    // process queue
                    $.each(files, function(i, file) {
                        if ( file.name.indexOf('.') === -1 ) {
                            // error message
                            $this.find('.filelist').append( '<div class="plupload-error">File extension error. <a href="#">Dismiss</a></div>' );
                            up.splice();
                        } else {
                            $this.find('.filelist').append(
                                '<div class="file" id="' + file.id + '"><b>' +
                                file.name + '</b> (<span>' + plupload.formatSize(0) + '</span>/' + plupload.formatSize(file.size) + ') ' +
                                '<div class="fileprogress"></div></div>');
                        }
                    });

                    up.refresh();
                    up.start();

                }

            });

            uploader.bind('UploadProgress', function(up, file) {
                $('#' + file.id + " .fileprogress").width(file.percent + "%");
                $('#' + file.id + " span").html(plupload.formatSize(parseInt(file.size * file.percent / 100)));
            });

            // initial error check before upload is attempted
            uploader.bind('Error', function(up, error){
                // console.log(up);
                // console.log(error);

                var errorMsg = '';

                // these initial checks are for wordpress defaults, in uploads.php there is a second
                // round of checks for per-field rules
                if (error["code"]=='-600')
                    errorMsg = 'File size error.';
                else if (error["code"]=='-601')
                    errorMsg = 'File extension error.';
                else
                    errorMsg = 'Upload error. Please reload the page and try again.';

                $this.find('.filelist').append( '<div class="plupload-error">' + errorMsg + ' <a href="#">Dismiss</a></div>' );

            });

            // a file was uploaded
            uploader.bind('FileUploaded', function(up, file, response) {

                // hide uploader status bar
                $('#' + file.id).fadeOut(function(){

                    // get handler response (attachment ID and URL)
                    response=response["response"];

                    // show errors
                    if(simian_is_json(response)) {

                        var responseObj = $.parseJSON(response);

                        // add it to the hidden field
                        if($this.hasClass("plupload-upload-uic-multiple")) {

                            // multiple ids
                            var v1=$.trim($("#" + fileid).val());
                            if(v1) {
                                v1 = v1 + "," + responseObj["attach_id"];
                            }
                            else {
                                v1 = responseObj["attach_id"];
                            }
                            $("#" + fileid).val(v1);

                            // multiple urls
                            var url1=$.trim($("#" + fileid + "-urls").val());
                            if(url1) {
                                url1 = url1 + "," + responseObj["url"];
                            } else {
                                url1 = responseObj["url"];
                            }
                            $("#" + fileid + "-urls").val(url1);

                        } else {

                            // @todo this may never occur

                            // only one id
                            $("#" + fileid).val(responseObj["attach_id"] + "");

                            // only one url
                            $("#" + fileid + "-urls").val(responseObj["url"]);

                        }

                    // show errors
                    } else {

                        // if our php handler found errors, spit them out here
                        $this.find('.filelist').append( '<div class="plupload-error">' + response + ' <a href="#">Dismiss</a></div>' );

                    }

                    // show thumbs
                    plu_show_file_info(fileid);

                });

            });

            uploader.bind('UploadComplete', function(up, files) {
                up.splice();
            });

        });

    }

    // simple click event
    $('.simian-submission, .simian-form-table').on('click', '.plupload-error a', function(event){
        event.preventDefault();
        $(this).parent().fadeOut();
    });

});

function plu_show_file_info(fileid){

    var thumbsC=jQuery("#" + fileid + "-plupload-thumbs");
    thumbsC.html("");

    // get urls from hidden field
    var imagesS=jQuery("#"+fileid+"-urls").val();
    var images=imagesS.split(",");

    // get attachment ids from hidden field
    var attachIdsS=jQuery("#"+fileid).val();
    var attachIds=attachIdsS.split(",");

    // build output
    for(var i=0; i<images.length; i++) {
        if(images[i]) {
            var thumb=jQuery('<div class="thumb" id="thumb-' + fileid + '-' + i + '"><div class="simian-plupload-handle"></div> <a id="simian-thumb-' + attachIds[i] + '" class="the-file" target="_blank" href="' + images[i] + '">' + simian_basename(images[i]) + '</a><span class="thumb-actions"><a id="remove-link-' + fileid + i + '" class="remove-link" href="#">Remove</a></span> <div class="clear"></div></div>');
            thumbsC.append(thumb);

            // file removal link
            thumb.find("a.remove-link").click(function() {
                var ki=jQuery(this).attr("id").replace("remove-link-" + fileid , "");
                ki=parseInt(ki);
                var kimages=[];
                var kIds=[];

                imagesS=jQuery("#"+fileid+"-urls").val();
                images=imagesS.split(",");

                attachIdsS=jQuery("#"+fileid).val();
                attachIds=attachIdsS.split(",");

                for(var j=0; j<images.length; j++) {
                    if(j != ki) {

                        // update image/ids array
                        kimages[kimages.length] = images[j];
                        kIds[kIds.length] = attachIds[j];

                    }
                }

                // update images/ids vals
                jQuery("#"+fileid+"-urls").val(kimages.join());
                jQuery("#"+fileid).val(kIds.join());

                plu_show_file_info(fileid);

                return false;
            });

        }
    }

    // enable sorting
    if(images.length > 1) {
        thumbsC.sortable({
            cursor: 'move',
            handle: '.simian-plupload-handle',
            update: function(event, ui) {

                var kimages=[];
                var kIds=[];
                var theClass = '';
                var attachID = '';

                thumbsC.find(".the-file").each(function() {

                    // refresh hidden url field
                    kimages[kimages.length]=jQuery(this).attr("href");

                    // get attach ID
                    theClass = jQuery(this).attr("id");
                    attachID = theClass.replace("simian-thumb-", "");

                    // refresh hidden attach ID field
                    kIds[kIds.length]=attachID;

                });

                jQuery("#"+fileid+"-urls").val(kimages.join());
                jQuery("#"+fileid).val(kIds.join());

                // show updated list
                plu_show_file_info(fileid);

            }
        });
        thumbsC.disableSelection();
    }

}

function simian_basename(path){
    return path.replace(/\\/g,'/').replace( /.*\//, '' );
}

function simian_is_json(str) {
    try {
        jQuery.parseJSON(str);
    } catch (e) {
        return false;
    }
    return true;
}