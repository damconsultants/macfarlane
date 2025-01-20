require([
  "jquery",
  "mage/url",
  "Magento_Ui/js/modal/modal",
  "mage/translate",
  "mage/adminhtml/events",
  "mage/adminhtml/wysiwyg/tiny_mce/setup",
], function ($, url, modal) {
  jQuery("body").append(
    '<div id="popup-modal" style="display:none;"><div id="compactViewContainer"></div></div>'
  );
  jQuery(document).on("click", ".icon-admin-pagebuilder-systems", function () {
    let interval = setInterval(function () {
      if (jQuery('div[data-index="video_source"]').length) {
        jQuery('div[data-index="video_source"]').append(
          '<button class="video_bynder_action_btn">Import Bynder Video</button>'
        );
        if (jQuery(".video_bynder_action_btn").length) {
          clearInterval(interval);
        }
      }
    }, 100);
  });
  jQuery(document).on("click", ".video_bynder_action_btn", function () {
    var baseUrl = window.BASE_URL;
    var url = new URL(baseUrl);
    var baseUrl = url.origin + "/";

    var AjaxU = baseUrl + "bynder/index";
    var docicon = "https://img.icons8.com/cotton/2x/regular-document.png";
    var p_id = jQuery(this).parent().parent().attr("id");
    var ident = "#";
    if (p_id == "" || p_id == undefined) {
      var res = jQuery(this).parent().parent().attr("class");
      if (res != undefined && res != "") {
        res = res.split(" ");
        res = Array.from(res);
        p_id = res[0];
        ident = ".";
      } else {
        p_id = undefined;
        return false;
      }
    }

    BynderCompactView.open({
      /* mode:"SingleSelect", */
      mode: "MultiSelect",
      assetTypes: ["video"],
      onSuccess: function (assets, additionalInfo) {
        console.log("Successfull Bynder Click...");
        var result = assets[0];
        var image_path = result.derivatives.webImage;
        console.log("assets");
        console.log(assets);

        var server_response = bynder_media_func(assets, additionalInfo);
        if (server_response) {
          return true;
        } else {
          return false;
        }

        function bynder_media_func(assets, a) {
          var asset = assets[0];
          var dataset_ids = [];
          var dataset_type = [];
          var video_url = [];

          $.each(assets, function (index, value) {
            dataset_ids.push(value.databaseId);
            dataset_type.push(value.type);
            if (value.__typename == "Video") {
              video_url[value.databaseId] = value.previewUrls[0];
            }
          });

          var bdomain = localStorage.getItem("cvad");
          if (typeof bdomain == "undefined" && bdomain == null) {
            alert("Something went wrong. Re-login system and try again...");
          }
          console.log(dataset_ids);
          console.log(dataset_type);
          console.log(video_url);

          $.ajax({
            showLoader: true,
            url: AjaxU,
            type: "POST",
            data: {
              databaseId: dataset_ids,
              bdomain: bdomain,
              datasetType: dataset_type,
            },
            dataType: "json",
          }).done(function (data) {
            console.log(data);

            var total_images = 0;
            if (data.status == 2) {
              alert(data.message);
              return false;
            } else if (data.status == 1) {
              var type_design = "";
              type_design +=
                '<div class="main-part bynder-imgbox-div">' +
                '<div class="middle-content">' +
                '<div class="main-one image-boxs" >';

              $.each(data.data, function (index, r) {
                $.each(r, function (i, res) {
                  var dataset_tag = '<img src="' + res.image_link + '">';
                  total_images++;

                  if (res.dataset_type == "VIDEO") {
                    dataset_tag =
                      '<video width="100%" controls><source src="' +
                      res.image_link +
                      '" type="video/mp4"><source src="' +
                      res.main_link +
                      '" type="video/ogg">Your browser does not support HTML video.</video>';
                  }

                  var dataset_size = "( Size: " + res.size + ")";
                  if (res.size == "0x0") {
                    dataset_size = " ";
                  }

                  if (res.size == "0x0" && res.dataset_type == "DOCUMENT") {
                    type_design +=
                      '<div class="m-box">' +
                      '<div class="m-img">' +
                      dataset_tag +
                      "</div>" +
                      '<div class="m-content">' +
                      '<input type="checkbox" class="image_types" id="image_type_' +
                      total_images +
                      '" name="image_type_' +
                      index +
                      '" value="' +
                      res.type +
                      index +
                      '">' +
                      '<label for="image_type_' +
                      total_images +
                      '">' +
                      res.type +
                      " " +
                      dataset_size +
                      "</label>" +
                      "</div>" +
                      "</div>";
                  }

                  if (
                    res.dataset_type == "IMAGE" ||
                    res.dataset_type == "VIDEO"
                  ) {
                    if (res.size != "0x0") {
                      type_design +=
                        '<div class="m-box">' +
                        '<div class="m-img">' +
                        dataset_tag +
                        "</div>" +
                        '<div class="m-content">' +
                        '<input type="checkbox" class="image_types" id="image_type_' +
                        total_images +
                        '" name="image_type_' +
                        index +
                        '" value="' +
                        res.type +
                        index +
                        '">' +
                        '<label for="image_type_' +
                        total_images +
                        '">' +
                        res.type +
                        " " +
                        dataset_size +
                        "</label>" +
                        "</div>" +
                        "</div>";
                    }
                  }
                });
              });
              type_design += "</div> </div> </div>";
              $("#compactViewContainer").html(type_design);

              var tag_html = "";
              var options = {
                type: "popup",
                responsive: true,
                innerScroll: true,
                title: "Select Bynder Image",
                buttons: [
                  {
                    text: $.mage.__("Continue"),
                    id: "selected_item_btn",
                    class: "",
                    click: function () {
                      var selected_types = [];
                      $(".image_types").each(function () {
                        var select_val = $(this).val();
                        if ($(this).prop("checked")) {
                          selected_types.push(select_val);
                        }
                      });

                      var database_videos_array = [];
                      if (selected_types.length > 0) {
                        $.each(data.data, function (index, r) {
                          var image_links_test = assets[index].url;
                          $.each(r, function (i, res) {
                            var type_val = res.type + index;
                            if ($.inArray(type_val, selected_types) != -1) {
                              console.log(res);
                              if (res.dataset_type == "IMAGE") {
                                tag_html +=
                                  '<img src="' +
                                  res.public_url +
                                  '" class="bynder-view" >';
                              } else if (res.dataset_type == "VIDEO") {
                                if (video_url[res.bynderid] != undefined) {
                                  var v_url = video_url[res.bynderid];
                                  tag_html += v_url;
                                }
                              } else if (res.dataset_type == "DOCUMENT") {
                                tag_html +=
                                  '<a href="' +
                                  res.main_link +
                                  '" class="doc-view"><span class="file-icon"><img src="' +
                                  docicon +
                                  '" width="20px" class="img-icon"></span>' +
                                  res.name +
                                  "</a>";
                              } else {
                              }
                            }
                          });
                        });

                        if (p_id != "" && p_id != undefined) {
                          jQuery("input[name='video_source']")
                            .val(tag_html)
                            .trigger("change");
                        } else {
                          console.log("else section");
                        }
                      } else {
                        alert("Sorry, you not selected any type ?");
                      }
                      this.closeModal();
                    },
                  },
                ],
              };
              var popup = modal(options, $("#popup-modal"));
              $("#popup-modal").modal("openModal");
              return true;
            } else {
            }
          });
        }
      },
    });
  });
});
