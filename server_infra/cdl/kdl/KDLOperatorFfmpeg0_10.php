<?php
/**
 * @package plugins.ffmpeg
 * @subpackage lib
 */
class KDLOperatorFfmpeg0_10 extends KDLOperatorFfmpeg {

	/* ---------------------------
	 * GenerateCommandLine
	 */
    public function GenerateCommandLine(KDLFlavor $design, KDLFlavor $target, $extra=null)
	{
		$cmdStr = parent::GenerateCommandLine($design, $target, $extra);
		if($target->_isTwoPass) {
	$pass2params = "-passlogfile ".KDLCmdlinePlaceholders::OutFileName.".2pass.log -pass";

$nullDev = "NUL";
$nullDev ="/dev/null";
			$pass1cmdLine =
				str_replace ( 
					array(KDLCmdlinePlaceholders::OutFileName, " -y"), 
					array($nullDev, " -an $pass2params 1 -fastfirstpass 1 -y"),
					$cmdStr);

			$pass2cmdLine =
				str_replace ( 
					array(" -y"), 
					array(" $pass2params 2 -y"),
					$cmdStr);
			$cmdStr = "$pass1cmdLine && ".KDLCmdlinePlaceholders::BinaryName." $pass2cmdLine ";
		}
		return $cmdStr;
	}
	
	/* ---------------------------
	 * generateVideoParams
	 */
    protected function generateVideoParams(KDLFlavor $design, KDLFlavor $target)
	{
		$cmdStr = parent::generateVideoParams($design, $target);
		if(!isset($target->_video))
			return $cmdStr;

$vid = $target->_video;
		if(isset($vid->_rotation)) {
			if($vid->_rotation==180)
				$cmdStr.= " -vf vflip,hflip";
			else if($vid->_rotation==90)
				$cmdStr.= " -vf transpose=1";
			else if($vid->_rotation==270 || $vid->_rotation==-90)
				$cmdStr.=" -vf transpose=2";
		}

		return $cmdStr;
	}
	
	/* ---------------------------
	 * getVideoCodecName
	 */
    protected function calcForcedKeyFrames($vidObj, KDLFlavor $target)
    {
		if(isset($vidObj->_gop) && isset($vidObj->_frameRate) && $vidObj->_frameRate>0){
			$gopInSecs=($vidObj->_gop/$vidObj->_frameRate);
			$forcedKF=KDLCmdlinePlaceholders::ForceKeyframes.($target->_container->_duration/1000)."_$gopInSecs";
			return " -force_key_frames $forcedKF";
		}
		return null;
    }
    
	/* ---------------------------
	 * getVideoCodecName
	 */
    protected function getVideoCodecSpecificParams(KDLFlavor $design, KDLFlavor $target)
	{
$vidObj = $target->_video;
$paramsStr = null;
		switch($vidObj->_id){
		case KDLVideoTarget::H264:
		case KDLVideoTarget::H264B:
			return parent::getVideoCodecSpecificParams($design, $target)
					." -vprofile baseline".$this->calcForcedKeyFrames($vidObj,$target)
					." -pix_fmt yuv420p";
		case KDLVideoTarget::H264M:
			return parent::getVideoCodecSpecificParams($design, $target)
					." -vprofile main".$this->calcForcedKeyFrames($vidObj,$target)
					." -pix_fmt yuv420p"					;
		case KDLVideoTarget::H264H:
			return parent::getVideoCodecSpecificParams($design, $target)
					." -vprofile high".$this->calcForcedKeyFrames($vidObj,$target)
					." -pix_fmt yuv420p";
		case KDLVideoTarget::APCO:
			return "prores -profile 0 -pix_fmt yuv422p10le";
		case KDLVideoTarget::APCS:
			return "prores -profile 1 -pix_fmt yuv422p10le";
		case KDLVideoTarget::APCN:
			return "prores -profile 2 -pix_fmt yuv422p10le";
		case KDLVideoTarget::APCH:
			return "prores -profile 3 -pix_fmt yuv422p10le";
		case KDLVideoTarget::DNXHD:
			return "dnxhd -mbd rd -pix_fmt yuv422p";
		default:
			return parent::getVideoCodecSpecificParams($design, $target)." -pix_fmt yuv420p";
		}
	}
	
	/* ---------------------------
	 * CheckConstraints
	 */
	public function CheckConstraints(KDLMediaDataSet $source, KDLFlavor $target, array &$errors=null, array &$warnings=null)
	{
	    if(KDLOperatorBase::CheckConstraints($source, $target, $errors, $warnings)==true)
			return true;

		if(!isset($target->_video))
			return false;
			
			/*
			 * HD codecs (prores & dnxhd) can be packaged only in MOV
			 */
$hdCodecsArr = array(KDLVideoTarget::APCO,KDLVideoTarget::APCS,KDLVideoTarget::APCN,KDLVideoTarget::APCH,KDLVideoTarget::DNXHD);
		if(isset($target->_container))
		{
			if($target->_container->_id!=KDLContainerTarget::MOV && in_array($target->_video->_id, $hdCodecsArr)){
				$target->_errors[KDLConstants::ContainerIndex][] = 
					KDLErrors::ToString(KDLErrors::PackageMovOnly, $target->_video->_id);
				return true;
			}
		}
		
			/*
			 * DNXHD - check validity of frame-size/bitrate mix
			 */
/*
Project Format	Resolution	Frame Size	Bits	FPS		<bitrate>
1080i / 59.94	DNxHD 220	1920 x 1080	8		29.97	220M
1080i / 59.94	DNxHD 145	1920 x 1080	8		29.97	145M
1080i / 50		DNxHD 185	1920 x 1080	8		25		185M
1080i / 50		DNxHD 120	1920 x 1080	8		25		120M
1080p / 25		DNxHD 185	1920 x 1080	8		25		185M
1080p / 25		DNxHD 120	1920 x 1080	8		25		120M
1080p / 25		DNxHD 36	1920 x 1080	8		25		36M
1080p / 24		DNxHD 175	1920 x 1080	8		24		175M
1080p / 24		DNxHD 115	1920 x 1080	8		24		115M
1080p / 24		DNxHD 36	1920 x 1080	8		24		36M
1080p / 23.976	DNxHD 175	1920 x 1080	8		23.976	175M
1080p / 23.976	DNxHD 115	1920 x 1080	8		23.976	115M
1080p / 23.976	DNxHD 36	1920 x 1080	8		23.976	36M
1080p / 29.7	DNxHD 45	1920 x 1080	8		29.97	45M

720p  / 59.94	DNxHD 220	1280 x 720	8		59.94	220M
720p  / 59.94	DNxHD 145	1280 x 720	8		59.94	145M
720p  / 50		DNxHD 175	1280 x 720	8		50		175M
720p  / 50		DNxHD 115	1280 x 720	8		50		115M
720p  / 23.976	DNxHD 90	1280 x 720	8		23.976	90M
720p  / 23.976	DNxHD 60	1280 x 720	8		23.976	60M
 */
		
		if($target->_video->_id==KDLVideoTarget::DNXHD) {
			if(!isset($target->_video->_width) || $target->_video->_width==0)
				$width = 0;
			else
				$width = $target->_video->_width;
			if(!isset($target->_video->_height) || $target->_video->_height==0)
				$height = 0;
			else
				$height = $target->_video->_height;
				
$dnxhd720BitratesArr = array(220000,145000,175000,115000,90000,60000);
$dnxhd1080BitratesArr = array(220000,145000,185000,120000,36000,175000,115000,45000);
			if(in_array($target->_video->_bitRate,$dnxhd720BitratesArr)
			&& (
				($width==1280 && $height==720) || ($width==1280 && $height==0) || ($width==0	&& $height==720)
			)){
KalturaLog::log("Supported DNXHD - br:".$this->_video->_bitRate.",w:$width,h:$height");
			}
			else if(in_array($target->_video->_bitRate,$dnxhd1080BitratesArr)
			&& (
				($width==1920 && $height==1080) || ($width==1920 && $height==0) || ($width==0	&& $height==1080)
			)){
KalturaLog::log("Supported DNXHD - br:".$target->_video->_bitRate.",w:$width,h:$height");
			}
			else {
				$str = "br:".$target->_video->_bitRate.",w:$width,h:$height";
				$target->_errors[KDLConstants::VideoIndex][] = 
					KDLErrors::ToString(KDLErrors::DnxhdUnsupportedParams, $str);
				return true;
			}
				
		}

		return $this->checkBasicFFmpegConstraints($source, $target, $errors, $warnings);
	}
}
	