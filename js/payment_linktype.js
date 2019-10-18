(function ($, drupalSettings) {
		'use strict';
	Drupal.behaviors.payment_linktypejs = {
		attach: function (context, settings) {
			var data = drupalSettings.payment_asp.orderData;
			// console.log(data.orderDetail['orderDetail0'].dtl_tax);

			//Get size of js object
					Object.size = function(obj) {
						var size = 0, key;
						for (key in obj) {
							if (obj.hasOwnProperty(key)) size++;
						}
						return size;
					};
			// START SHA1 Hashing code
			/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
			/*  SHA-1 implementation in JavaScript | (c) Chris Veness 2002-2010 | www.movable-type.co.uk      */
			/*   - see http://csrc.nist.gov/groups/ST/toolkit/secure_hashing.html                             */
			/*         http://csrc.nist.gov/groups/ST/toolkit/examples.html                                   */
			/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */

			var Sha1 = {};  // Sha1 namespace

			/**
			 * Generates SHA-1 hash of string
			 *
			 * @param {String} msg                String to be hashed
			 * @param {Boolean} [utf8encode=true] Encode msg as UTF-8 before generating hash
			 * @returns {String}                  Hash of msg as hex character string
			 */
			Sha1.hash = function(msg, utf8encode) {
			  utf8encode =  (typeof utf8encode == 'undefined') ? true : utf8encode;
			  
			  // convert string to UTF-8, as SHA only deals with byte-streams
			  if (utf8encode) msg = Utf8.encode(msg);
			  
			  // constants [§4.2.1]
			  var K = [0x5a827999, 0x6ed9eba1, 0x8f1bbcdc, 0xca62c1d6];
			  
			  // PREPROCESSING 
			  
			  msg += String.fromCharCode(0x80);  // add trailing '1' bit (+ 0's padding) to string [§5.1.1]
			  
			  // convert string msg into 512-bit/16-integer blocks arrays of ints [§5.2.1]
			  var l = msg.length/4 + 2;  // length (in 32-bit integers) of msg + ‘1’ + appended length
			  var N = Math.ceil(l/16);   // number of 16-integer-blocks required to hold 'l' ints
			  var M = new Array(N);
			  
			  for (var i=0; i<N; i++) {
				M[i] = new Array(16);
				for (var j=0; j<16; j++) {  // encode 4 chars per integer, big-endian encoding
				  M[i][j] = (msg.charCodeAt(i*64+j*4)<<24) | (msg.charCodeAt(i*64+j*4+1)<<16) | 
					(msg.charCodeAt(i*64+j*4+2)<<8) | (msg.charCodeAt(i*64+j*4+3));
				} // note running off the end of msg is ok 'cos bitwise ops on NaN return 0
			  }
			  // add length (in bits) into final pair of 32-bit integers (big-endian) [§5.1.1]
			  // note: most significant word would be (len-1)*8 >>> 32, but since JS converts
			  // bitwise-op args to 32 bits, we need to simulate this by arithmetic operators
			  M[N-1][14] = ((msg.length-1)*8) / Math.pow(2, 32); M[N-1][14] = Math.floor(M[N-1][14])
			  M[N-1][15] = ((msg.length-1)*8) & 0xffffffff;
			  
			  // set initial hash value [§5.3.1]
			  var H0 = 0x67452301;
			  var H1 = 0xefcdab89;
			  var H2 = 0x98badcfe;
			  var H3 = 0x10325476;
			  var H4 = 0xc3d2e1f0;
			  
			  // HASH COMPUTATION [§6.1.2]
			  
			  var W = new Array(80); var a, b, c, d, e;
			  for (var i=0; i<N; i++) {
			  
				// 1 - prepare message schedule 'W'
				for (var t=0;  t<16; t++) W[t] = M[i][t];
				for (var t=16; t<80; t++) W[t] = Sha1.ROTL(W[t-3] ^ W[t-8] ^ W[t-14] ^ W[t-16], 1);
				
				// 2 - initialise five working variables a, b, c, d, e with previous hash value
				a = H0; b = H1; c = H2; d = H3; e = H4;
				
				// 3 - main loop
				for (var t=0; t<80; t++) {
				  var s = Math.floor(t/20); // seq for blocks of 'f' functions and 'K' constants
				  var T = (Sha1.ROTL(a,5) + Sha1.f(s,b,c,d) + e + K[s] + W[t]) & 0xffffffff;
				  e = d;
				  d = c;
				  c = Sha1.ROTL(b, 30);
				  b = a;
				  a = T;
				}
				
				// 4 - compute the new intermediate hash value
				H0 = (H0+a) & 0xffffffff;  // note 'addition modulo 2^32'
				H1 = (H1+b) & 0xffffffff; 
				H2 = (H2+c) & 0xffffffff; 
				H3 = (H3+d) & 0xffffffff; 
				H4 = (H4+e) & 0xffffffff;
			  }

			  return Sha1.toHexStr(H0) + Sha1.toHexStr(H1) + 
				Sha1.toHexStr(H2) + Sha1.toHexStr(H3) + Sha1.toHexStr(H4);
			}

			//
			// function 'f' [§4.1.1]
			//
			Sha1.f = function(s, x, y, z)  {
			  switch (s) {
			  case 0: return (x & y) ^ (~x & z);           // Ch()
			  case 1: return x ^ y ^ z;                    // Parity()
			  case 2: return (x & y) ^ (x & z) ^ (y & z);  // Maj()
			  case 3: return x ^ y ^ z;                    // Parity()
			  }
			}

			//
			// rotate left (circular left shift) value x by n positions [§3.2.5]
			//
			Sha1.ROTL = function(x, n) {
			  return (x<<n) | (x>>>(32-n));
			}

			//
			// hexadecimal representation of a number 
			//   (note toString(16) is implementation-dependant, and  
			//   in IE returns signed numbers when used on full words)
			//
			Sha1.toHexStr = function(n) {
			  var s="", v;
			  for (var i=7; i>=0; i--) { v = (n>>>(i*4)) & 0xf; s += v.toString(16); }
			  return s;
			}

			/* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -  */
				// END SHA1 Hashing code
				// START encode64 code

				/* /_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/
				charset = shift_jis

				+++ Base64 Encode / Decode +++


				LastModified : 2006-11/08
				
				Powered by kerry
				http://202.248.69.143/~goma/
				
				動作ブラウザ :: IE4+ , NN4.06+ , Gecko , Opera6+


				* [RFC 2045] Multipurpose Internet Mail Extensions
										(MIME) Part One:
							   Format of Internet Message Bodies
				ftp://ftp.isi.edu/in-notes/rfc2045.txt
				
				/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/
				
				*   Usage:

				// エンコード
				b64_string = base64.encode( my_data [, strMode] );
				
				// デコード
				my_data = base64.decode( b64_string [, strMode] );   
				
				
				strMode -> 入力データが文字列の場合 1 を
				
				/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/ */


			base64 = new function()
			{
				var utfLibName  = "utf";
				var b64char     = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
				var b64encTable = b64char.split("");
				var b64decTable = [];
				for (var i=0; i<b64char.length; i++) b64decTable[b64char.charAt(i)] = i;

				this.encode = function(_dat, _strMode)
				{
					return encoder( _strMode? unpackUTF8(_dat): unpackChar(_dat) );
				}
				
				var encoder = function(_ary)
				{
					var md  = _ary.length % 3;
					var b64 = "";
					var i, tmp = 0;
					
					if (md) for (i=3-md; i>0; i--) _ary[_ary.length] = 0;
					
					for (i=0; i<_ary.length; i+=3)
					{
						tmp = (_ary[i]<<16) | (_ary[i+1]<<8) | _ary[i+2];
						b64 +=  b64encTable[ (tmp >>>18) & 0x3f]
							+   b64encTable[ (tmp >>>12) & 0x3f]
							+   b64encTable[ (tmp >>> 6) & 0x3f]
							+   b64encTable[ tmp & 0x3f];
					}

					if (md) // 3の倍数にパディングした 0x0 分 = に置き換え
					{
						md = 3- md;
						b64 = b64.substr(0, b64.length- md);
						while (md--) b64 += "=";
					}
					
					return b64;
				}
				
				this.decode = function(_b64, _strMode)
				{
					var tmp = decoder( _b64 );
					return _strMode? packUTF8(tmp): packChar(tmp);
				}
				
				var decoder = function(_b64)
				{
					_b64    = _b64.replace(/[^A-Za-z0-9\+\/]/g, "");
					var md  = _b64.length % 4;
					var j, i, tmp;
					var dat = [];
					
					// replace 時 = も削っている。その = の代わりに 0x0 を補間
					if (md) for (i=0; i<4-md; i++) _b64 += "A";
					
					for (j=i=0; i<_b64.length; i+=4, j+=3)
					{
						tmp = (b64decTable[_b64.charAt( i )] <<18)
							| (b64decTable[_b64.charAt(i+1)] <<12)
							| (b64decTable[_b64.charAt(i+2)] << 6)
							|  b64decTable[_b64.charAt(i+3)];
						dat[ j ]    = tmp >>> 16;
						dat[j+1]    = (tmp >>> 8) & 0xff;
						dat[j+2]    = tmp & 0xff;
					}
					// 補完された 0x0 分削る
					if (md) dat.length -= [0,0,2,1][md];

					return dat;
				}
				
				var packUTF8    = function(_x){ return window[utfLibName].packUTF8(_x) };
				var unpackUTF8  = function(_x){ return window[utfLibName].unpackUTF8(_x) };
				var packChar    = function(_x){ return window[utfLibName].packChar(_x) };
				var unpackChar  = function(_x){ return window[utfLibName].unpackChar(_x) };
			}
				/* /_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/
				charset = shift_jis
				+++ UTF8/16 ライブラリ +++
				LastModified : 2006-10/16
				Powered by kerry
				http://202.248.69.143/~goma/
				動作ブラウザ :: IE4+ , NN4.06+ , Gecko , Opera6+
				* [RFC 2279] UTF-8, a transformation format of ISO 10646
				ftp://ftp.isi.edu/in-notes/rfc2279.txt
				* [RFC 1738] Uniform Resource Locators (URL)
				ftp://ftp.isi.edu/in-notes/rfc1738.txt
				/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/
				Usage:
				// 文字列を UTF16 (文字コード) へ
				utf16code_array = utf.unpackUTF16( my_string );
				// 文字列を UTF8 (文字コード) へ
				utf8code_array = utf.unpackUTF8( my_string );
				// UTF8 (文字コード) から文字列へ。 utf.unpackUTF8() したものを元に戻す
				my_string = utf.packUTF8( utf8code_array );
				// UTF8/16 (文字コード) を文字列へ
				my_string = utf.packChar( utfCode_array );
				// UTF16 (文字コード) から UTF8 (文字コード) へ
				utf8code_array = utf.toUTF8( utf16code_array );
				// UTF8 (文字コード) から UTF16 (文字コード) へ
				utf16code_array = utf.toUTF16( utf8code_array );
				// URL 文字列へエンコード
				url_string = utf.URLencode( my_string );
				// URL 文字列からデコード
				my_string = utf.URLdecode( url_string );
				/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/_/ */
			utf = new function()
			{
				this.unpackUTF16 = function(_str)
				{
					var i, utf16=[];
					for (i=0; i<_str.length; i++) utf16[i] = _str.charCodeAt(i);
					return utf16;
				}
				
				this.unpackChar = function(_str) 
				{
					var utf16 = this.unpackUTF16(_str);
					var i,n, tmp = [];
					for (n=i=0; i<utf16.length; i++) {
						if (utf16[i]<=0xff) tmp[n++] = utf16[i];
						else {
							tmp[n++] = utf16[i] >> 8;
							tmp[n++] = utf16[i] &  0xff;
						}   
					}
					return tmp;
				}
				
				this.packChar  =
				this.packUTF16 = function(_utf16)
				{
					var i, str = "";
					for (i in _utf16) str += String.fromCharCode(_utf16[i]);
					return str;
				}

				this.unpackUTF8 = function(_str)
				{
				   return this.toUTF8( this.unpackUTF16(_str) );
				}

				this.packUTF8 = function(_utf8)
				{
					return this.packUTF16( this.toUTF16(_utf8) );
				}
				
				this.toUTF8 = function(_utf16)
				{
					var utf8 = [];
					var idx = 0;
					var i, j, c;
					for (i=0; i<_utf16.length; i++)
					{
						c = _utf16[i];
						if (c <= 0x7f) utf8[idx++] = c;
						else if (c <= 0x7ff)
						{
							utf8[idx++] = 0xc0 | (c >>> 6 );
							utf8[idx++] = 0x80 | (c & 0x3f);
						}
						else if (c <= 0xffff)
						{
							utf8[idx++] = 0xe0 | (c >>> 12 );
							utf8[idx++] = 0x80 | ((c >>> 6 ) & 0x3f);
							utf8[idx++] = 0x80 | (c & 0x3f);
						}
						else
						{
							j = 4;
							while (c >> (6*j)) j++;
							utf8[idx++] = ((0xff00 >>> j) & 0xff) | (c >>> (6*--j) );
							while (j--) 
							utf8[idx++] = 0x80 | ((c >>> (6*j)) & 0x3f);
						}
					}
					return utf8;
				}
				
				this.toUTF16 = function(_utf8)
				{
					var utf16 = [];
					var idx = 0;
					var i,s;
					for (i=0; i<_utf8.length; i++, idx++)
					{
						if (_utf8[i] <= 0x7f) utf16[idx] = _utf8[i];
						else 
						{
							if ( (_utf8[i]>>5) == 0x6)
							{
								utf16[idx] = ( (_utf8[i] & 0x1f) << 6 )
											 | ( _utf8[++i] & 0x3f );
							}
							else if ( (_utf8[i]>>4) == 0xe)
							{
								utf16[idx] = ( (_utf8[i] & 0xf) << 12 )
											 | ( (_utf8[++i] & 0x3f) << 6 )
											 | ( _utf8[++i] & 0x3f );
							}
							else
							{
								s = 1;
								while (_utf8[i] & (0x20 >>> s) ) s++;
								utf16[idx] = _utf8[i] & (0x1f >>> s);
								while (s-->=0) utf16[idx] = (utf16[idx] << 6) ^ (_utf8[++i] & 0x3f);
							}
						}
					}
					return utf16;
				}
				
				this.URLencode = function(_str)
				{
					return _str.replace(/([^a-zA-Z0-9_\-\.])/g, function(_tmp, _c)
						{ 
							if (_c == "\x20") return "+";
							var tmp = utf.toUTF8( [_c.charCodeAt(0)] );
							var c = "";
							for (var i in tmp)
							{
								i = tmp[i].toString(16);
								if (i.length == 1) i = "0"+ i;
								c += "%"+ i;
							}
							return c;
						} );
				}

				this.URLdecode = function(_dat)
				{
					_dat = _dat.replace(/\+/g, "\x20");
					_dat = _dat.replace( /%([a-fA-F0-9][a-fA-F0-9])/g, 
							function(_tmp, _hex){ return String.fromCharCode( parseInt(_hex, 16) ) } );
					return this.packChar( this.toUTF16( this.unpackUTF16(_dat) ) );
				}
			}
				  // END encode64 code
				f_submit();

					function f_submit() { 

					var order = new Order();
					order.pay_method            = "";
					order.merchant_id           = "30132";
					order.service_id            = "103";
					order.cust_code             = "Merchant_TestUser_999999";
					order.sps_cust_no           = "";
					order.sps_payment_no        = "";
					order.order_id              = "734beca56f016d1776fd45c0d229b057";
					order.item_id               = "T_0003";
					order.pay_item_id           = "";
					order.item_name             = "テスト商品";
					order.tax                   = "";
					order.amount                = "1";
					order.pay_type              = "0";
					order.auto_charge_type      = "";
					order.service_type          = "0";
					order.div_settele           = "";
					order.last_charge_month     = "";
					order.camp_type             = "";
					order.tracking_id           = "";
					order.terminal_type         = "0";
					order.success_url           = "http://stbfep.sps-system.com/MerchantPaySuccess.jsp";
					order.cancel_url            = "http://stbfep.sps-system.com/MerchantPayCancel.jsp";
					order.error_url             = "http://stbfep.sps-system.com/MerchantPayError.jsp";
					order.pagecon_url           = "http://stbfep.sps-system.com/MerchantPayResultRecieveSuccess.jsp";
					order.free1                 = "";
					order.free2                 = "";
					order.free3                 = "";
					order.free_csv_input        =
						"LAST_NAME=鈴木,FIRST_NAME=太郎,LAST_NAME_KANA=スズキ,FIRST_NAME_KANA=タロウ,FIRST_ZIP=210,SECOND_ZIP=0001,ADD1=岐阜県,ADD2=あああ市あああ町,ADD3=,TEL=12345679801,MAIL=aaaa@bb.jp,ITEM_NAME=TEST ITEM";
					order.request_date          = getYYYYMMDDHHMMSS();
					order.limit_second          = "";
					order.hashkey               = "ed679e1c9f90c2ab96b25d5c580b58e25192eb5d";

					var orderDetail = new OrderDetail();
					orderDetail.dtl_rowno       = "1";
					orderDetail.dtl_item_id     = "dtlItem_1";
					orderDetail.dtl_item_name   = "明細商品名1";
					orderDetail.dtl_item_count  = "1";
					orderDetail.dtl_tax         = "1";
					orderDetail.dtl_amount      = "1";
					orderDetail.dtl_free1       = "";
					orderDetail.dtl_free2       = "";
					orderDetail.dtl_free3       = "";
					order.orderDetail.push(orderDetail);

					// console.log(order.orderDetail);
					// order.free_csv2              = encode64(order.free_csv_input);
					// フリーCSV
					order.free_csv              = base64.encode(order.free_csv_input, 1);

					//チェックサム
					order.sps_hashcode          = Sha1.hash( order.toString() );

					feppost(order);
					}

					// オブジェクト定義[OrderDetail]
					function OrderDetail() {
						this.toString = function() {
							var result =
								this.dtl_rowno +
								this.dtl_item_id +
								this.dtl_item_name +
								this.dtl_item_count +
								this.dtl_tax +
								this.dtl_amount +
								this.dtl_free1 +
								this.dtl_free2 +
								this.dtl_free3;
							return result;
						}
					}

					// オブジェクト定義[Order]
					function Order() {
						this.orderDetail = new Array();
						this.toString = function() {

							var resultOrderDetail = "";
							for (i = 0; i < this.orderDetail.length; i++) {
								resultOrderDetail = resultOrderDetail + this.orderDetail[i].toString();
							}

							var result =
								this.pay_method +
								this.merchant_id +
								this.service_id +
								this.cust_code +
								this.sps_cust_no +
								this.sps_payment_no +
								this.order_id +
								this.item_id +
								this.pay_item_id +
								this.item_name +
								this.tax +
								this.amount +
								this.pay_type +
								this.auto_charge_type +
								this.service_type +
								this.div_settele +
								this.last_charge_month +
								this.camp_type +
								this.tracking_id +
								this.terminal_type +
								this.success_url +
								this.cancel_url +
								this.error_url +
								this.pagecon_url +
								this.free1 +
								this.free2 +
								this.free3 +
								this.free_csv +
								resultOrderDetail +
								this.request_date +
								this.limit_second +
								this.hashkey;
							return result;
						};
					}

					// 日時の取得
					function getYYYYMMDDHHMMSS() {
						var now = new Date();
						return now.getFullYear() + zeroPadding(now.getMonth() + 1) + zeroPadding(now.getDate()) +
							   zeroPadding(now.getHours()) + zeroPadding(now.getMinutes()) + zeroPadding(now.getSeconds());
					}

					function zeroPadding(num) {
						if (num < 10) { num = "0" + num; }
						return num + "";
					}

					function feppost(order) {

						var connectUrl = "https://stbfep.sps-system.com/Extra/PayRequestAction.do";
						// var connectUrl = "https://stbfep.sps-system.com/f01/FepBuyInfoReceive.do";

						var form =
							$('<form></form>',{action:connectUrl,method:'POST'}).hide();

						var body = $('body');
						body.append(form);
						form.append($('<input>',{type:'hidden',name:'pay_method'        ,value:order.pay_method                  }));
						form.append($('<input>',{type:'hidden',name:'merchant_id'       ,value:order.merchant_id                 }));
						form.append($('<input>',{type:'hidden',name:'service_id'        ,value:order.service_id                  }));
						form.append($('<input>',{type:'hidden',name:'cust_code'         ,value:order.cust_code                   }));
						form.append($('<input>',{type:'hidden',name:'sps_cust_no'       ,value:order.sps_cust_no                 }));
						form.append($('<input>',{type:'hidden',name:'sps_payment_no'    ,value:order.sps_payment_no              }));
						form.append($('<input>',{type:'hidden',name:'order_id'          ,value:order.order_id                    }));
						form.append($('<input>',{type:'hidden',name:'item_id'           ,value:order.item_id                     }));
						form.append($('<input>',{type:'hidden',name:'pay_item_id'       ,value:order.pay_item_id                 }));
						form.append($('<input>',{type:'hidden',name:'item_name'         ,value:order.item_name                   }));
						form.append($('<input>',{type:'hidden',name:'tax'               ,value:order.tax                         }));
						form.append($('<input>',{type:'hidden',name:'amount'            ,value:order.amount                      }));
						form.append($('<input>',{type:'hidden',name:'pay_type'          ,value:order.pay_type                    }));
						form.append($('<input>',{type:'hidden',name:'auto_charge_type'  ,value:order.auto_charge_type            }));
						form.append($('<input>',{type:'hidden',name:'service_type'      ,value:order.service_type                }));
						form.append($('<input>',{type:'hidden',name:'div_settele'       ,value:order.div_settele                 }));
						form.append($('<input>',{type:'hidden',name:'last_charge_month' ,value:order.last_charge_month           }));
						form.append($('<input>',{type:'hidden',name:'camp_type'         ,value:order.camp_type                   }));
						form.append($('<input>',{type:'hidden',name:'tracking_id'       ,value:order.tracking_id                 }));
						form.append($('<input>',{type:'hidden',name:'terminal_type'     ,value:order.terminal_type               }));
						form.append($('<input>',{type:'hidden',name:'success_url'       ,value:order.success_url                 }));
						form.append($('<input>',{type:'hidden',name:'cancel_url'        ,value:order.cancel_url                  }));
						form.append($('<input>',{type:'hidden',name:'error_url'         ,value:order.error_url                   }));
						form.append($('<input>',{type:'hidden',name:'pagecon_url'       ,value:order.pagecon_url                 }));
						form.append($('<input>',{type:'hidden',name:'free1'             ,value:order.free1                       }));
						form.append($('<input>',{type:'hidden',name:'free2'             ,value:order.free2                       }));
						form.append($('<input>',{type:'hidden',name:'free3'             ,value:order.free3                       }));
						form.append($('<input>',{type:'hidden',name:'free_csv'          ,value:order.free_csv                    }));
						form.append($('<input>',{type:'hidden',name:'request_date'      ,value:order.request_date                }));
						form.append($('<input>',{type:'hidden',name:'limit_second'      ,value:order.limit_second                }));
						form.append($('<input>',{type:'hidden',name:'hashkey'           ,value:order.hashkey                     }));
						form.append($('<input>',{type:'hidden',name:'sps_hashcode'      ,value:order.sps_hashcode                }));

						for (i = 0; i < order.orderDetail.length; i++) {
							form.append($('<input>',{type:'hidden',name:'dtl_rowno'         ,value:order.orderDetail[i].dtl_rowno             }));
							form.append($('<input>',{type:'hidden',name:'dtl_item_id'       ,value:order.orderDetail[i].dtl_item_id           }));
							form.append($('<input>',{type:'hidden',name:'dtl_item_name'     ,value:order.orderDetail[i].dtl_item_name         }));
							form.append($('<input>',{type:'hidden',name:'dtl_item_count'    ,value:order.orderDetail[i].dtl_item_count        }));
							form.append($('<input>',{type:'hidden',name:'dtl_tax'           ,value:order.orderDetail[i].dtl_tax               }));
							form.append($('<input>',{type:'hidden',name:'dtl_amount'        ,value:order.orderDetail[i].dtl_amount            }));
							form.append($('<input>',{type:'hidden',name:'dtl_free1'         ,value:order.orderDetail[i].dtl_free1             }));
							form.append($('<input>',{type:'hidden',name:'dtl_free2'         ,value:order.orderDetail[i].dtl_free2             }));
							form.append($('<input>',{type:'hidden',name:'dtl_free3'         ,value:order.orderDetail[i].dtl_free3             }));
						}
						// form.submit();
					}
		}
	};
}(jQuery, drupalSettings));