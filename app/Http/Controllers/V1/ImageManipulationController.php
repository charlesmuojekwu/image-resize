<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ImageManipulation;
use App\Http\Requests\StoreImageManipulationRequest;
use App\Http\Requests\UpdateImageManipulationRequest;
use App\Http\Resources\V1\ImageManipulationResource;
use App\Models\Album;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageManipulationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return ImageManipulationResource::collection(ImageManipulation::where(['user_id' => auth()->id()])->paginate(5));
    }


    public function byAlbum(Album $album)
    {
        $where = [
            'album_id' => $album->id
        ];
        return ImageManipulationResource::collection(ImageManipulation::where($where)->paginate());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreImageManipulationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function resize(StoreImageManipulationRequest $request)
    {
        $all = $request->all();

        $image = $all['image'];
        unset($all['image']);
        $data = [
            'type' => ImageManipulation::TYPE_RESIZE,
            'data' => json_encode($all),
            'user_id' => null
        ];

        if(isset($all['album_id'])) 
        {
            $data['album_id'] = $all['album_id'];
        }

        $dir = 'images/'.Str::random().'/';
        $absolutePath = public_path($dir);
        File::makeDirectory($absolutePath);

        if ($image instanceof UploadedFile){
            $data['name'] = $image->getClientOriginalName();
            // transform test.jpg -> test-resized.jpg
            $filename = pathinfo($data['name'], PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();
            $orignalPath = $absolutePath.$data['name'];

            $image->move($absolutePath, $data['name']);
            $data['path'] = $dir.$data['name'];

        } else {
            $data['name'] = pathinfo($image, PATHINFO_BASENAME);
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);
            $orignalPath = $absolutePath.$data['name'];

            copy($image, $orignalPath);
        }
        $data['path'] = $dir.$data['name'];

        $w = $all['w'];
        $h = $all['h'] ?? false;

        list($width, $height, $image) = $this->getImageWidthAndHeight($w, $h, $orignalPath);

        $resizedFilename = $filename.'-resized.'.$extension;

        $image->resize($width, $height)->save($absolutePath.$resizedFilename);
        $data['output_path'] = $dir.$resizedFilename;

        $imageManipulation = ImageManipulation::create($data);

        return new ImageManipulationResource($imageManipulation);

        //return $imageManipulation;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function show(ImageManipulation $image)
    {
        return new ImageManipulationResource($image);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function destroy(ImageManipulation $image)
    {
        $image->delete();
        return response('', 204);
    }

    protected function getImageWidthAndHeight($w, $h,string $originalPath )
    {
        $image = Image::make($originalPath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        if (str_ends_with($w, '%')) {
            $ratioW = str_replace('%', '', $w);
            $ratioH = $h ? (float)str_replace('%', '', $h) : $ratioW;

            $newWidth = $originalWidth * $ratioW / 100;
            $newHeight = $originalHeight * $ratioH / 100;
        } else {
            $newWidth = (float)$w;
            $newHeight = $h ? (float)$h : $originalHeight * $newWidth/$originalWidth;
        }

        return [$newWidth, $newHeight, $image];
    }
}
